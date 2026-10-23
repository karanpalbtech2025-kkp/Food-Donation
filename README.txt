FOODSHARE — HTML, CSS, JAVASCRIPT + NODE.JS + MYSQL / MONGODB

PROJECT CONTENTS
- public/index.html, public/style.css, public/app.js: frontend
- server.js: Node.js/Express API
- mysql-schema.sql: MySQL table schema
- .env.example: database configuration template

IMPORTANT DATABASE NOTE
You can configure this project to use MySQL OR MongoDB. It does not write to both databases simultaneously. Choose one in DATABASE_TYPE. The API stores each record in a table (MySQL) or stores the project state in MongoDB. This is a simple academic/demo persistence layer, not a production-grade multi-user system.

REQUIREMENTS
- Node.js 18 or newer
- Either MySQL Server OR MongoDB Server / MongoDB Atlas

SETUP
1. Extract the ZIP.
2. Open a terminal in the extracted project folder.
3. Install dependencies:
   npm install
4. Copy .env.example to .env.
   macOS/Linux: cp .env.example .env
   Windows: copy .env.example .env

MYSQL OPTION
1. Start your MySQL service.
2. Edit .env and set:
   DATABASE_TYPE=mysql
   MYSQL_HOST=localhost
   MYSQL_PORT=3306
   MYSQL_USER=root
   MYSQL_PASSWORD=your_actual_password
   MYSQL_DATABASE=foodshare
3. The server creates the database and required tables automatically. The supplied mysql-schema.sql is also available if you want to create them manually.

MONGODB OPTION
1. Start MongoDB locally, or create a MongoDB Atlas cluster.
2. Edit .env:
   DATABASE_TYPE=mongodb
   MONGODB_URI=mongodb://127.0.0.1:27017/foodshare
   For Atlas, use your own Atlas connection string and credentials.
3. The server creates the application collection when the first record is saved.

RUN
npm start
Open http://localhost:3000

VERIFY CONNECTION
Open http://localhost:3000/api/health. It should return JSON showing ok:true and your selected database.

HOW IT WORKS
- The browser fetches saved project data from /api/state at startup.
- Changes are written to the selected database through the Express API.
- If the API is not reachable, the frontend falls back to localStorage for the current browser.

SECURITY / NEXT STEPS
This is an educational prototype. Before real deployment, add authentication and role-based authorization, input validation, CSRF/rate limiting as appropriate, and proper per-record REST endpoints. Do not expose database passwords or commit .env. Do not use this demo as a production multi-user food safety or personal-data system.
