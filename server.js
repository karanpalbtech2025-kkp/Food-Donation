require("dotenv").config();
const express = require("express");
const path = require("path");
const mysql = require("mysql2/promise");
const mongoose = require("mongoose");

const app = express();
const PORT = Number(process.env.PORT || 3000);
const DB_TYPE = String(process.env.DATABASE_TYPE || "mysql").toLowerCase();
const COLLECTIONS = ["donations", "requests", "allocations", "categories", "users"];
const EMPTY_STATE = { donations: [], requests: [], allocations: [], categories: [], users: [] };

app.use(express.json({ limit: "2mb" }));
app.use(express.static(path.join(__dirname, "public")));

let pool;
let StateModel;

async function connectMySQL() {
  const config = {
    host: process.env.MYSQL_HOST || "localhost",
    port: Number(process.env.MYSQL_PORT || 3306),
    user: process.env.MYSQL_USER || "root",
    password: process.env.MYSQL_PASSWORD || "",
    waitForConnections: true,
    connectionLimit: 10
  };
  const setup = await mysql.createConnection(config);
  await setup.query(`CREATE DATABASE IF NOT EXISTS \`${(process.env.MYSQL_DATABASE || "foodshare").replace(/[^a-zA-Z0-9_]/g, "")}\``);
  await setup.end();
  pool = mysql.createPool({ ...config, database: process.env.MYSQL_DATABASE || "foodshare" });
  for (const table of COLLECTIONS) {
    await pool.query(`CREATE TABLE IF NOT EXISTS \`${table}\` (
      id BIGINT NOT NULL PRIMARY KEY,
      payload JSON NOT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )`);
  }
  console.log("Connected to MySQL.");
}

async function connectMongo() {
  await mongoose.connect(process.env.MONGODB_URI || "mongodb://127.0.0.1:27017/foodshare");
  const schema = new mongoose.Schema({
    collectionName: { type: String, required: true, unique: true },
    payload: { type: mongoose.Schema.Types.Mixed, required: true }
  }, { timestamps: true });
  StateModel = mongoose.models.FoodShareState || mongoose.model("FoodShareState", schema);
  console.log("Connected to MongoDB.");
}

async function readState() {
  const state = { ...EMPTY_STATE };
  if (DB_TYPE === "mysql") {
    for (const table of COLLECTIONS) {
      const [rows] = await pool.query(`SELECT payload FROM \`${table}\` ORDER BY id ASC`);
      state[table] = rows.map(row => typeof row.payload === "string" ? JSON.parse(row.payload) : row.payload);
    }
  } else {
    const doc = await StateModel.findOne({ collectionName: "main" }).lean();
    if (doc && doc.payload) {
      for (const key of COLLECTIONS) state[key] = Array.isArray(doc.payload[key]) ? doc.payload[key] : [];
    }
  }
  return state;
}

async function writeState(input) {
  const state = {};
  for (const key of COLLECTIONS) state[key] = Array.isArray(input[key]) ? input[key] : [];
  if (DB_TYPE === "mysql") {
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      for (const table of COLLECTIONS) {
        await conn.query(`DELETE FROM \`${table}\``);
        for (const item of state[table]) {
          const id = Number(item.id);
          if (!Number.isSafeInteger(id) || id < 1) continue;
          await conn.query(`INSERT INTO \`${table}\` (id, payload) VALUES (?, ?)`, [id, JSON.stringify(item)]);
        }
      }
      await conn.commit();
    } catch (error) {
      await conn.rollback();
      throw error;
    } finally {
      conn.release();
    }
  } else {
    await StateModel.findOneAndUpdate(
      { collectionName: "main" },
      { $set: { payload: state } },
      { upsert: true, new: true, runValidators: true }
    );
  }
  return state;
}

app.get("/api/health", async (_req, res) => {
  res.json({ ok: true, database: DB_TYPE });
});

app.get("/api/state", async (_req, res) => {
  try { res.json(await readState()); }
  catch (error) {
    console.error("Read state error:", error);
    res.status(500).json({ error: "Could not read project data from database." });
  }
});

app.put("/api/state", async (req, res) => {
  try {
    const state = await writeState(req.body || {});
    res.json({ ok: true, state });
  } catch (error) {
    console.error("Write state error:", error);
    res.status(500).json({ error: "Could not save project data to database." });
  }
});

async function start() {
  if (DB_TYPE === "mysql") await connectMySQL();
  else if (DB_TYPE === "mongodb" || DB_TYPE === "mongo") await connectMongo();
  else throw new Error('DATABASE_TYPE must be "mysql" or "mongodb".');
  app.listen(PORT, () => console.log(`FoodShare running at http://localhost:${PORT} (database: ${DB_TYPE})`));
}
start().catch(error => {
  console.error("Startup failed:", error.message);
  console.error("Check your database service and .env settings.");
  process.exit(1);
});
