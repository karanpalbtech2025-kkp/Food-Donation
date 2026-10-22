//package com.foodshare;

import java.awt.*;
import java.awt.event.KeyAdapter;
import java.awt.event.KeyEvent;
import java.awt.event.MouseAdapter;
import java.awt.event.MouseEvent;
import java.sql.*;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Vector;
import java.util.concurrent.Callable;
import java.util.function.Consumer;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import javax.swing.*;
import javax.swing.border.EmptyBorder;
import javax.swing.event.DocumentEvent;
import javax.swing.event.DocumentListener;
import javax.swing.table.DefaultTableModel;
import javax.swing.table.TableColumn;
import javax.swing.table.TableRowSorter;


class User {
    protected int userId;
    protected String fullName;
    protected String email;
    protected String role;
    protected int profileId; // DonorID / VolunteerID of this login (0 for admins)

    public User(int userId, String fullName, String email, String role) {
        this.userId = userId;
        this.fullName = fullName;
        this.email = email;
        this.role = role;
    }
}


class Donor extends User {
    private String donorType;
    private String city;
    private String address;

    public Donor(int userId, String fullName, String email, String donorType, String city, String address) {
        super(userId, fullName, email, "Donor");
        this.donorType = donorType;
        this.city = city;
        this.address = address;
    }

    public String getDonorType() { return donorType; }
    public String getCity()      { return city; }
    public String getAddress()   { return address; }
}


class DatabaseManager {
    private static final String URL = "jdbc:mysql://localhost:3306/fooddonationdistributiondb";
    private static final String USER = "root";
    private static final String PASS = "root123";   // <-- keep YOUR MySQL password here

    public static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(URL, USER, PASS);
    }

    // JDBC Read Operation (Authenticates User)
    public static User authenticateUser(String email, String password) throws Exception {
        String sql = "SELECT * FROM `USER` WHERE LOWER(TRIM(Email)) = ?";
        try (Connection conn = getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql)) {

            stmt.setString(1, email.toLowerCase().trim());
            ResultSet rs = stmt.executeQuery();

            if (rs.next()) {
                String dbPass = rs.getString("PasswordHash");
                if (password.equals(dbPass)) {
                    User u = new User(rs.getInt("UserID"), rs.getString("FullName"),
                                      rs.getString("Email"), rs.getString("Role"));
                    u.profileId = lookupProfileId(conn, u);
                    return u;
                }
            }
            return null;
        } catch (SQLException e) { // 3. Exception Handling (1 M)
            throw new Exception("Database Error: " + e.getMessage());
        }
    }

    
    private static int lookupProfileId(Connection conn, User u) throws SQLException {
        String r = u.role == null ? "" : u.role.toLowerCase();
        String sql;
        if (r.contains("donor")) sql = "SELECT DonorID FROM DONOR WHERE UserID = ?";
        else if (r.contains("admin")) return 0;
        else sql = "SELECT VolunteerID FROM NGO_VOLUNTEER WHERE UserID = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, u.userId);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? rs.getInt(1) : 0;
            }
        }
    }

    
    public static boolean registerDonor(Donor donor, String phone, String password) throws Exception {
        Connection conn = null;
        try {
            conn = getConnection();
            conn.setAutoCommit(false); 

            String sqlUser = "INSERT INTO `USER` (FullName, Email, PasswordHash, PhoneNumber, Role) VALUES (?, ?, ?, ?, 'DONOR')";
            int newUserId = 0;
            try (PreparedStatement stmtUser = conn.prepareStatement(sqlUser, Statement.RETURN_GENERATED_KEYS)) {
                stmtUser.setString(1, donor.fullName);
                stmtUser.setString(2, donor.email);
                stmtUser.setString(3, password);
                stmtUser.setString(4, phone);
                stmtUser.executeUpdate();
                try (ResultSet rsKeys = stmtUser.getGeneratedKeys()) {
                    if (rsKeys.next()) newUserId = rsKeys.getInt(1);
                }
            }

            String sqlDonor = "INSERT INTO DONOR (UserID, DonorType, Address, City) VALUES (?, ?, ?, ?)";
            try (PreparedStatement stmtDonor = conn.prepareStatement(sqlDonor)) {
                stmtDonor.setInt(1, newUserId);
                stmtDonor.setString(2, donor.getDonorType());
                stmtDonor.setString(3, donor.getAddress());
                stmtDonor.setString(4, donor.getCity());
                stmtDonor.executeUpdate();
            }

            conn.commit(); 
            return true;
        } catch (SQLException e) {
            if (conn != null) conn.rollback();
            throw new Exception("Registration Failed: " + e.getMessage());
        } finally {
            if (conn != null) {
                conn.setAutoCommit(true);
                conn.close();
            }
        }
    }

   
    public static List<User> getAllUsers() {
        List<User> userList = new ArrayList<>(); // 2. Collections (1 M)
        String sql = "SELECT UserID, FullName, Email, Role FROM `USER` LIMIT 10";
        try (Connection conn = getConnection();
             Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                userList.add(new User(rs.getInt("UserID"), rs.getString("FullName"),
                                      rs.getString("Email"), rs.getString("Role")));
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return userList;
    }

    
    static class ColInfo {
        String name, type, def, refTable, refCol;
        boolean nullable, isPk, auto, generated, hidden;
        String lowerType() { return type == null ? "" : type.toLowerCase(); }
        boolean isEnum() { return lowerType().startsWith("enum"); }
        boolean isRequired() { return !nullable && def == null && !auto && !generated; }
    }

    static class Item {
        final Object value;
        final String label;
        Item(Object value, String label) { this.value = value; this.label = label; }
        @Override public String toString() { return label; }
    }

    static class TableMeta {
        final List<ColInfo> cols = new ArrayList<>();
        String ownerCol; // column that links a row to the logged-in user (DonorID / VolunteerID)
        ColInfo find(String n) {
            for (ColInfo c : cols) if (c.name.equalsIgnoreCase(n)) return c;
            return null;
        }
    }

    private static final java.util.Set<String> HIDDEN =
            new java.util.HashSet<>(java.util.Arrays.asList("passwordhash", "password"));

    private static String friendly(SQLException e) {
        switch (e.getErrorCode()) {
            case 1451: return "This record is used by other records, so it can't be deleted.";
            case 1452: return "The selected linked record doesn't exist.";
            case 1062: return "A record with the same unique value already exists.";
            case 1048: return "A required field was left empty.";
            default:   return e.getMessage();
        }
    }

    private static List<ColInfo> readCols(Connection conn, String table) throws SQLException {
        List<ColInfo> list = new ArrayList<>();
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery("SHOW COLUMNS FROM `" + table + "`")) {
            while (rs.next()) {
                ColInfo c = new ColInfo();
                c.name = rs.getString("Field");
                c.type = rs.getString("Type");
                c.nullable = "YES".equalsIgnoreCase(rs.getString("Null"));
                c.isPk = "PRI".equalsIgnoreCase(rs.getString("Key"));
                c.def = rs.getString("Default");
                String extra = rs.getString("Extra") == null ? "" : rs.getString("Extra").toLowerCase();
                c.auto = extra.contains("auto_increment");
                c.generated = extra.contains("generated")
                        || (c.def != null && c.def.toUpperCase().startsWith("CURRENT_TIMESTAMP"));
                c.hidden = HIDDEN.contains(c.name.toLowerCase());
                list.add(c);
            }
        }
        return list;
    }

    private static TableMeta readMeta(Connection conn, String table, String role, boolean ownOnly) throws SQLException {
        TableMeta meta = new TableMeta();
        meta.cols.addAll(readCols(conn, table));

        String fk = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME "
                  + "FROM information_schema.KEY_COLUMN_USAGE "
                  + "WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = LOWER(?) AND REFERENCED_TABLE_NAME IS NOT NULL";
        try (PreparedStatement ps = conn.prepareStatement(fk)) {
            ps.setString(1, table);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    ColInfo c = meta.find(rs.getString(1));
                    if (c != null && c.refTable == null) {
                        c.refTable = rs.getString(2);
                        c.refCol = rs.getString(3);
                    }
                }
            }
        }

        if (ownOnly) {
            String[] cands = role != null && role.toLowerCase().contains("donor")
                    ? new String[]{"DonorID"} : new String[]{"VolunteerID"};
            for (String cand : cands) {
                ColInfo c = meta.find(cand);
                if (c != null) { meta.ownerCol = c.name; break; }
            }
        }
        return meta;
    }

    public static TableMeta getMeta(String table, String role, boolean ownOnly) throws Exception {
        try (Connection conn = getConnection()) {
            return readMeta(conn, table, role, ownOnly);
        } catch (SQLException e) {
            throw new Exception("Could not read " + table + ": " + friendly(e));
        }
    }

    
    public static DefaultTableModel fetchTable(String table, User user, boolean ownOnly) throws Exception {
        try (Connection conn = getConnection()) {
            TableMeta meta = readMeta(conn, table, user.role, ownOnly);
            StringBuilder sql = new StringBuilder("SELECT ");
            Vector<String> names = new Vector<>();
            for (ColInfo c : meta.cols) {
                if (c.hidden) continue;
                if (!names.isEmpty()) sql.append(", ");
                sql.append('`').append(c.name).append('`');
                names.add(c.name);
            }
            sql.append(" FROM `").append(table).append('`');
            if (meta.ownerCol != null) sql.append(" WHERE `").append(meta.ownerCol).append("` = ?");

            try (PreparedStatement ps = conn.prepareStatement(sql.toString())) {
                if (meta.ownerCol != null) ps.setInt(1, user.profileId);
                try (ResultSet rs = ps.executeQuery()) {
                    Vector<Vector<Object>> rows = new Vector<>();
                    while (rs.next()) {
                        Vector<Object> row = new Vector<>();
                        for (int i = 1; i <= names.size(); i++) row.add(rs.getObject(i));
                        rows.add(row);
                    }
                    return new DefaultTableModel(rows, names) {
                        @Override public boolean isCellEditable(int r, int c) { return false; }
                    };
                }
            }
        } catch (SQLException e) {
            throw new Exception("Could not load " + table + ": " + friendly(e));
        }
    }

   
    public static List<Item> referenceItems(ColInfo c) throws Exception {
        List<Item> items = new ArrayList<>();
        try (Connection conn = getConnection()) {
            List<ColInfo> rc = readCols(conn, c.refTable);
            String up = c.refTable.toUpperCase();
            boolean hasUser = false;
            for (ColInfo x : rc) if (x.name.equalsIgnoreCase("UserID")) hasUser = true;

            String sql;
            if (hasUser && (up.equals("DONOR") || up.equals("NGO_VOLUNTEER") || up.equals("ADMIN"))) {
                sql = "SELECT t.`" + c.refCol + "`, u.FullName FROM `" + c.refTable
                    + "` t JOIN `USER` u ON u.UserID = t.UserID";
            } else {
                String label = null;
                for (ColInfo x : rc) {
                    if (!x.isPk && !x.hidden && x.lowerType().startsWith("varchar")
                            && !x.name.toLowerCase().contains("email")) { label = x.name; break; }
                }
                String lab = label == null ? c.refCol : label;
                sql = "SELECT `" + c.refCol + "`, `" + lab + "` FROM `" + c.refTable + "`";
            }
            try (Statement st = conn.createStatement(); ResultSet rs = st.executeQuery(sql)) {
                while (rs.next()) {
                    Object id = rs.getObject(1);
                    String lab = rs.getString(2);
                    String text = (lab == null || lab.equals(String.valueOf(id)))
                            ? String.valueOf(id) : lab + " (#" + id + ")";
                    items.add(new Item(id, text));
                }
            }
        } catch (SQLException e) {
            throw new Exception("Could not load options: " + friendly(e));
        }
        return items;
    }

    public static void insertRow(String table, Map<String, Object> values) throws Exception {
        if (values.isEmpty()) throw new Exception("Nothing to save.");
        StringBuilder cols = new StringBuilder(), qs = new StringBuilder();
        for (String k : values.keySet()) {
            if (cols.length() > 0) { cols.append(", "); qs.append(", "); }
            cols.append('`').append(k).append('`');
            qs.append('?');
        }
        execute("INSERT INTO `" + table + "` (" + cols + ") VALUES (" + qs + ")", new ArrayList<>(values.values()));
    }

    public static void updateRow(String table, Map<String, Object> values, Map<String, Object> where) throws Exception {
        if (values.isEmpty()) throw new Exception("Nothing to save.");
        if (where.isEmpty()) throw new Exception("This table has no primary key, so it can't be edited safely.");
        StringBuilder set = new StringBuilder(), cond = new StringBuilder();
        List<Object> params = new ArrayList<>();
        for (Map.Entry<String, Object> e : values.entrySet()) {
            if (set.length() > 0) set.append(", ");
            set.append('`').append(e.getKey()).append("` = ?");
            params.add(e.getValue());
        }
        for (Map.Entry<String, Object> e : where.entrySet()) {
            if (cond.length() > 0) cond.append(" AND ");
            cond.append('`').append(e.getKey()).append("` = ?");
            params.add(e.getValue());
        }
        execute("UPDATE `" + table + "` SET " + set + " WHERE " + cond, params);
    }

    public static void deleteRow(String table, Map<String, Object> where) throws Exception {
        if (where.isEmpty()) throw new Exception("This table has no primary key, so it can't be edited safely.");
        StringBuilder cond = new StringBuilder();
        for (String k : where.keySet()) {
            if (cond.length() > 0) cond.append(" AND ");
            cond.append('`').append(k).append("` = ?");
        }
        execute("DELETE FROM `" + table + "` WHERE " + cond, new ArrayList<>(where.values()));
    }

    private static void execute(String sql, List<Object> params) throws Exception {
        try (Connection c = getConnection(); PreparedStatement ps = c.prepareStatement(sql)) {
            for (int i = 0; i < params.size(); i++) ps.setObject(i + 1, params.get(i));
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new Exception(friendly(e));
        }
    }
}


class Util {
    // Runs a database job on a background thread, then reports back on the UI thread
    static <T> void run(Callable<T> job, Consumer<T> ok, Consumer<String> fail) {
        new SwingWorker<T, Void>() {
            @Override protected T doInBackground() throws Exception { return job.call(); }
            @Override protected void done() {
                try {
                    ok.accept(get());
                } catch (java.util.concurrent.ExecutionException ex) {
                    Throwable t = ex.getCause() != null ? ex.getCause() : ex;
                    fail.accept(t.getMessage() == null ? t.toString() : t.getMessage());
                } catch (InterruptedException ex) {
                    fail.accept("Interrupted.");
                }
            }
        }.execute();
    }

    
    static String pretty(String name) {
        return name.replace('_', ' ').replaceAll("(?<=[a-z])(?=[A-Z])", " ");
    }
}


class RecordDialog extends JDialog {
    private final Map<String, JComponent> inputs = new LinkedHashMap<>();
    private final Map<String, DatabaseManager.ColInfo> infos = new LinkedHashMap<>();
    private final JLabel error = new JLabel(" ");
    private final JButton save = new JButton("Save");
    private final String table;
    private final DatabaseManager.TableMeta meta;
    private final User user;
    private final Map<String, Object> existing;
    private final Runnable onSaved;

    RecordDialog(Window owner, String title, String table, DatabaseManager.TableMeta meta, User user,
                 Map<String, Object> existing, Runnable onSaved) throws Exception {
        super(owner, title, Dialog.ModalityType.APPLICATION_MODAL);
        this.table = table;
        this.meta = meta;
        this.user = user;
        this.existing = existing;
        this.onSaved = onSaved;
        boolean editing = existing != null;

        JPanel form = new JPanel(new GridBagLayout());
        form.setBorder(new EmptyBorder(20, 24, 10, 24));
        GridBagConstraints g = new GridBagConstraints();
        g.insets = new Insets(6, 6, 6, 6);
        g.anchor = GridBagConstraints.WEST;
        int row = 0;
        for (DatabaseManager.ColInfo c : meta.cols) {
            if (c.hidden || c.auto || c.generated) continue;
            if (c.name.equalsIgnoreCase(meta.ownerCol)) continue; // set automatically from the login
            if (editing && c.isPk) continue;                      // keys can't be changed
            JComponent input = buildInput(c, editing ? existing.get(c.name) : null, editing);
            inputs.put(c.name, input);
            infos.put(c.name, c);

            g.gridx = 0; g.gridy = row; g.weightx = 0; g.fill = GridBagConstraints.NONE;
            form.add(new JLabel(Util.pretty(c.name) + (c.isRequired() ? " *" : "")), g);
            g.gridx = 1; g.weightx = 1; g.fill = GridBagConstraints.HORIZONTAL;
            form.add(input, g);
            row++;
        }
        if (inputs.isEmpty()) form.add(new JLabel("There is nothing to edit here."));

        error.setForeground(new Color(200, 30, 30));
        JButton cancel = new JButton("Cancel");
        cancel.addActionListener(e -> dispose());
        save.setBackground(new Color(25, 135, 84));
        save.setForeground(Color.WHITE);
        save.setFocusPainted(false);
        save.setEnabled(!inputs.isEmpty());
        save.addActionListener(e -> onSave());

        JPanel buttons = new JPanel(new FlowLayout(FlowLayout.RIGHT, 8, 0));
        buttons.add(cancel);
        buttons.add(save);
        JPanel bottom = new JPanel(new BorderLayout(0, 8));
        bottom.setBorder(new EmptyBorder(0, 24, 18, 24));
        bottom.add(error, BorderLayout.NORTH);
        bottom.add(buttons, BorderLayout.SOUTH);

        setLayout(new BorderLayout());
        add(form, BorderLayout.CENTER);
        add(bottom, BorderLayout.SOUTH);
        getRootPane().setDefaultButton(save);
        getRootPane().registerKeyboardAction(e -> dispose(), KeyStroke.getKeyStroke("ESCAPE"),
                JComponent.WHEN_IN_FOCUSED_WINDOW);
        pack();
        setMinimumSize(new Dimension(440, getHeight()));
        setLocationRelativeTo(owner);
    }

    private JComponent buildInput(DatabaseManager.ColInfo c, Object current, boolean editing) throws Exception {
        if (c.refTable != null) { // foreign key -> drop-down with readable names
            JComboBox<DatabaseManager.Item> cb = new JComboBox<>();
            if (c.nullable) cb.addItem(new DatabaseManager.Item(null, "-- none --"));
            for (DatabaseManager.Item it : DatabaseManager.referenceItems(c)) cb.addItem(it);
            if (current != null) {
                for (int i = 0; i < cb.getItemCount(); i++) {
                    Object v = cb.getItemAt(i).value;
                    if (v != null && String.valueOf(v).equals(String.valueOf(current))) cb.setSelectedIndex(i);
                }
            }
            return cb;
        }
        if (c.isEnum()) { // e.g. Status -> drop-down
            JComboBox<String> cb = new JComboBox<>();
            Matcher m = Pattern.compile("'((?:[^']|'')*)'").matcher(c.type);
            while (m.find()) cb.addItem(m.group(1).replace("''", "'"));
            Object pre = editing ? current : c.def;
            if (pre != null) cb.setSelectedItem(String.valueOf(pre));
            return cb;
        }
        JTextField tf = new JTextField(24);
        String t = c.lowerType();
        boolean dateTime = t.startsWith("datetime") || t.startsWith("timestamp");
        if (editing) {
            if (current != null) {
                String s = String.valueOf(current);
                tf.setText(dateTime ? s.replaceAll("\\.0$", "") : s);
            }
        } else if (c.def != null && !c.generated) {
            tf.setText(c.def);
        } else if (t.equals("date")) {
            tf.setText(LocalDate.now().toString());
        } else if (dateTime) {
            tf.setText(LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")));
        }
        if (t.equals("date")) tf.setToolTipText("Format: yyyy-mm-dd");
        if (dateTime) tf.setToolTipText("Format: yyyy-mm-dd hh:mm:ss");
        return tf;
    }

    private Object convert(DatabaseManager.ColInfo c, String s) throws Exception {
        String t = c.lowerType();
        try {
            if (t.startsWith("int") || t.startsWith("bigint") || t.startsWith("smallint")
                    || t.startsWith("tinyint") || t.startsWith("mediumint")) return Long.parseLong(s);
            if (t.startsWith("decimal") || t.startsWith("numeric") || t.startsWith("float")
                    || t.startsWith("double")) return new java.math.BigDecimal(s);
        } catch (NumberFormatException e) {
            throw new Exception("must be a number");
        }
        return s;
    }

    private void fail(String msg) {
        error.setText("<html><body style='width:340px'>" + msg + "</body></html>");
        pack();
    }

    private void onSave() {
        boolean editing = existing != null;
        Map<String, Object> vals = new LinkedHashMap<>();
        for (Map.Entry<String, JComponent> e : inputs.entrySet()) {
            DatabaseManager.ColInfo c = infos.get(e.getKey());
            JComponent comp = e.getValue();
            Object raw;
            if (comp instanceof JComboBox) {
                Object sel = ((JComboBox<?>) comp).getSelectedItem();
                raw = sel instanceof DatabaseManager.Item ? ((DatabaseManager.Item) sel).value : sel;
            } else {
                String s = ((JTextField) comp).getText().trim();
                raw = s.isEmpty() ? null : s;
            }
            if (raw == null) {
                if (!c.nullable) {
                    if (editing || c.def == null) { fail(Util.pretty(c.name) + " is required."); return; }
                    continue; // new record: let the database default apply
                }
                vals.put(c.name, null);
                continue;
            }
            if (raw instanceof String) {
                try {
                    raw = convert(c, (String) raw);
                } catch (Exception ex) {
                    fail(Util.pretty(c.name) + " " + ex.getMessage() + ".");
                    return;
                }
            }
            vals.put(c.name, raw);
        }

        save.setEnabled(false);
        error.setText(" ");
        Util.run(() -> {
            if (editing) {
                Map<String, Object> where = new LinkedHashMap<>();
                for (DatabaseManager.ColInfo c : meta.cols) if (c.isPk) where.put(c.name, existing.get(c.name));
                if (meta.ownerCol != null) where.put(meta.ownerCol, user.profileId);
                DatabaseManager.updateRow(table, vals, where);
            } else {
                if (meta.ownerCol != null) vals.put(meta.ownerCol, user.profileId);
                DatabaseManager.insertRow(table, vals);
            }
            return Boolean.TRUE;
        }, ok -> { dispose(); onSaved.run(); }, msg -> { save.setEnabled(true); fail(msg); });
    }
}


class DashboardPanel extends JPanel {
    private static final Color NAVBAR = new Color(20, 40, 30);
    private static final Color GREEN = new Color(25, 135, 84);
    private static final Color DARK = new Color(33, 37, 41);
    private static final Color RED = new Color(200, 45, 60);
    private static final Color BG = new Color(245, 245, 245);

    private final User user;
    private final CardLayout pageLayout = new CardLayout();
    private final JPanel pages = new JPanel(pageLayout);
    private final Map<String, TablePage> tablePages = new LinkedHashMap<>();
    private final Map<String, JLabel> statValues = new LinkedHashMap<>();
    private final List<JButton> navButtons = new ArrayList<>();

    // {menu label, table, ownOnly, permissions: C=add U=edit D=delete}
    private final List<Object[]> menu = new ArrayList<>();
    // {card title, table, ownOnly, menu label to open when clicked}
    private final List<Object[]> stats = new ArrayList<>();

    DashboardPanel(User user, Runnable onLogout) {
        this.user = user;
        setLayout(new BorderLayout());
        configureForRole();

        add(buildTopBar(onLogout), BorderLayout.NORTH);
        add(buildSidebar(), BorderLayout.WEST);

        pages.setBackground(BG);
        pages.add(buildOverview(), "Overview");
        for (Object[] m : menu) {
            String label = (String) m[0];
            TablePage page = new TablePage(label, (String) m[1], (Boolean) m[2], (String) m[3]);
            tablePages.put(label, page);
            pages.add(page, label);
        }
        add(pages, BorderLayout.CENTER);
        showPage("Overview");
    }

    private void configureForRole() {
        String r = user.role == null ? "" : user.role.toLowerCase();
        if (r.contains("admin")) {
            menu.add(new Object[]{"Users", "USER", false, "UD"});
            menu.add(new Object[]{"Donors", "DONOR", false, "CUD"});
            menu.add(new Object[]{"Volunteers", "NGO_VOLUNTEER", false, "CUD"});
            menu.add(new Object[]{"Food Categories", "FOOD_CATEGORY", false, "CUD"});
            menu.add(new Object[]{"Donations", "DONATION", false, "CUD"});
            menu.add(new Object[]{"Food Requests", "FOOD_REQUEST", false, "CUD"});
            menu.add(new Object[]{"Allocations", "ALLOCATION", false, "CUD"});
            menu.add(new Object[]{"Distributions", "DISTRIBUTION", false, "CUD"});

            stats.add(new Object[]{"Total Users", "USER", false, "Users"});
            stats.add(new Object[]{"Donors", "DONOR", false, "Donors"});
            stats.add(new Object[]{"Volunteers", "NGO_VOLUNTEER", false, "Volunteers"});
            stats.add(new Object[]{"Donations", "DONATION", false, "Donations"});
            stats.add(new Object[]{"Food Requests", "FOOD_REQUEST", false, "Food Requests"});
            stats.add(new Object[]{"Distributions", "DISTRIBUTION", false, "Distributions"});
        } else if (r.contains("donor")) {
            menu.add(new Object[]{"My Donations", "DONATION", true, "CUD"});
            menu.add(new Object[]{"Food Categories", "FOOD_CATEGORY", false, ""});
            menu.add(new Object[]{"Distributions", "DISTRIBUTION", false, ""});

            stats.add(new Object[]{"My Donations", "DONATION", true, "My Donations"});
            stats.add(new Object[]{"Food Requests", "FOOD_REQUEST", false, null});
            stats.add(new Object[]{"Distributions", "DISTRIBUTION", false, "Distributions"});
        } else { // NGO volunteer
            menu.add(new Object[]{"Available Donations", "DONATION", false, ""});
            menu.add(new Object[]{"My Requests", "FOOD_REQUEST", true, "CUD"});
            menu.add(new Object[]{"Allocations", "ALLOCATION", false, ""});
            menu.add(new Object[]{"Distributions", "DISTRIBUTION", true, "U"});

            stats.add(new Object[]{"Available Donations", "DONATION", false, "Available Donations"});
            stats.add(new Object[]{"My Requests", "FOOD_REQUEST", true, "My Requests"});
            stats.add(new Object[]{"Distributions", "DISTRIBUTION", true, "Distributions"});
        }
    }

    private JButton button(String text, Color bg) {
        JButton b = new JButton(text);
        b.setBackground(bg);
        b.setForeground(Color.WHITE);
        b.setFocusPainted(false);
        return b;
    }

    private JPanel buildTopBar(Runnable onLogout) {
        JPanel bar = new JPanel(new BorderLayout());
        bar.setBackground(NAVBAR);
        bar.setBorder(new EmptyBorder(10, 20, 10, 20));

        JLabel brand = new JLabel("\u2665 Food Share Portal");
        brand.setForeground(new Color(40, 167, 69));
        brand.setFont(new Font("Segoe UI", Font.BOLD, 18));
        bar.add(brand, BorderLayout.WEST);

        JPanel right = new JPanel(new FlowLayout(FlowLayout.RIGHT, 12, 0));
        right.setOpaque(false);
        JLabel who = new JLabel(user.fullName + "  |  " + user.role);
        who.setForeground(Color.WHITE);
        who.setFont(new Font("Segoe UI", Font.PLAIN, 14));
        JButton logout = button("Logout", GREEN);
        logout.addActionListener(e -> onLogout.run());
        right.add(who);
        right.add(logout);
        bar.add(right, BorderLayout.EAST);
        return bar;
    }

    private JPanel buildSidebar() {
        JPanel side = new JPanel();
        side.setLayout(new BoxLayout(side, BoxLayout.Y_AXIS));
        side.setBackground(DARK);
        side.setBorder(new EmptyBorder(15, 10, 15, 10));
        side.setPreferredSize(new Dimension(210, 0));

        side.add(navButton("Overview"));
        side.add(Box.createVerticalStrut(6));
        for (Object[] m : menu) {
            side.add(navButton((String) m[0]));
            side.add(Box.createVerticalStrut(6));
        }
        return side;
    }

    private JButton navButton(String label) {
        JButton b = new JButton(label);
        b.setMaximumSize(new Dimension(Integer.MAX_VALUE, 40));
        b.setAlignmentX(Component.LEFT_ALIGNMENT);
        b.setHorizontalAlignment(SwingConstants.LEFT);
        b.setFont(new Font("Segoe UI", Font.PLAIN, 14));
        b.setForeground(Color.WHITE);
        b.setBackground(DARK);
        b.setFocusPainted(false);
        b.setBorder(new EmptyBorder(8, 14, 8, 14));
        b.addActionListener(e -> showPage(label));
        navButtons.add(b);
        return b;
    }

    private void showPage(String name) {
        pageLayout.show(pages, name);
        for (JButton b : navButtons) b.setBackground(b.getText().equals(name) ? GREEN : DARK);
        if (name.equals("Overview")) loadStats();
        else tablePages.get(name).load(null);
    }

    
    private JPanel buildOverview() {
        JPanel page = new JPanel(new BorderLayout());
        page.setBackground(BG);
        page.setBorder(new EmptyBorder(25, 30, 25, 30));

        JLabel welcome = new JLabel("Welcome, " + user.fullName + "!");
        welcome.setFont(new Font("Segoe UI", Font.BOLD, 26));
        JLabel sub = new JLabel("Logged in as " + user.role + "  \u2022  " + user.email
                + "  \u2022  click a card to open it");
        sub.setForeground(Color.GRAY);

        JPanel head = new JPanel();
        head.setLayout(new BoxLayout(head, BoxLayout.Y_AXIS));
        head.setOpaque(false);
        head.setBorder(new EmptyBorder(0, 0, 20, 0));
        head.add(welcome);
        head.add(Box.createVerticalStrut(4));
        head.add(sub);
        page.add(head, BorderLayout.NORTH);

        JPanel grid = new JPanel(new GridLayout(0, 3, 18, 18));
        grid.setOpaque(false);
        Color[] palette = {GREEN, DARK, new Color(13, 110, 253), new Color(220, 53, 69),
                           new Color(255, 145, 0), new Color(111, 66, 193)};
        int i = 0;
        for (Object[] s : stats) {
            String title = (String) s[0];
            String target = (String) s[3];
            JPanel card = new JPanel(new BorderLayout());
            card.setBackground(palette[i++ % palette.length]);
            card.setBorder(new EmptyBorder(22, 22, 22, 22));
            JLabel value = new JLabel("...");
            value.setForeground(Color.WHITE);
            value.setFont(new Font("Segoe UI", Font.BOLD, 38));
            JLabel label = new JLabel(title);
            label.setForeground(Color.WHITE);
            label.setFont(new Font("Segoe UI", Font.PLAIN, 15));
            card.add(value, BorderLayout.CENTER);
            card.add(label, BorderLayout.SOUTH);
            if (target != null) {
                card.setCursor(Cursor.getPredefinedCursor(Cursor.HAND_CURSOR));
                card.addMouseListener(new MouseAdapter() {
                    @Override public void mouseClicked(MouseEvent e) { showPage(target); }
                });
            }
            statValues.put(title, value);
            grid.add(card);
        }
        JPanel gridWrap = new JPanel(new BorderLayout());
        gridWrap.setOpaque(false);
        gridWrap.add(grid, BorderLayout.NORTH);
        page.add(gridWrap, BorderLayout.CENTER);
        return page;
    }

    private void loadStats() {
        Util.run(() -> {
            Map<String, String> result = new LinkedHashMap<>();
            for (Object[] s : stats) {
                try {
                    result.put((String) s[0], String.valueOf(
                            DatabaseManager.fetchTable((String) s[1], user, (Boolean) s[2]).getRowCount()));
                } catch (Exception ex) {
                    result.put((String) s[0], "-");
                }
            }
            return result;
        }, res -> {
            for (Map.Entry<String, String> e : res.entrySet()) {
                JLabel l = statValues.get(e.getKey());
                if (l != null) l.setText(e.getValue());
            }
        }, msg -> { });
    }

    
    private class TablePage extends JPanel {
        private final String title, table;
        private final boolean ownOnly, canAdd, canEdit, canDelete;
        private final JTable jTable = new JTable();
        private final JLabel status = new JLabel(" ");
        private final JTextField search = new JTextField(16);
        private final JButton btnAdd = button("+ Add New", GREEN);
        private final JButton btnEdit = button("Edit", DARK);
        private final JButton btnDelete = button("Delete", RED);
        private final JButton btnRefresh = button("Refresh", DARK);
        private DefaultTableModel model;
        private TableRowSorter<DefaultTableModel> sorter;
        private DatabaseManager.TableMeta meta;
        private boolean writable = true;

        TablePage(String title, String table, boolean ownOnly, String perms) {
            this.title = title;
            this.table = table;
            this.ownOnly = ownOnly;
            this.canAdd = perms.contains("C");
            this.canEdit = perms.contains("U");
            this.canDelete = perms.contains("D");

            setLayout(new BorderLayout(0, 12));
            setBackground(BG);
            setBorder(new EmptyBorder(25, 30, 20, 30));

            JLabel heading = new JLabel(title);
            heading.setFont(new Font("Segoe UI", Font.BOLD, 24));
            JPanel searchBox = new JPanel(new FlowLayout(FlowLayout.RIGHT, 6, 0));
            searchBox.setOpaque(false);
            searchBox.add(new JLabel("Search:"));
            searchBox.add(search);
            JPanel titleRow = new JPanel(new BorderLayout());
            titleRow.setOpaque(false);
            titleRow.add(heading, BorderLayout.WEST);
            titleRow.add(searchBox, BorderLayout.EAST);

            JPanel toolbar = new JPanel(new FlowLayout(FlowLayout.LEFT, 8, 0));
            toolbar.setOpaque(false);
            if (canAdd) toolbar.add(btnAdd);
            if (canEdit) toolbar.add(btnEdit);
            if (canDelete) toolbar.add(btnDelete);
            toolbar.add(btnRefresh);
            if (!canAdd && !canEdit && !canDelete) {
                JLabel ro = new JLabel("  View only");
                ro.setForeground(Color.GRAY);
                toolbar.add(ro);
            }

            JPanel north = new JPanel();
            north.setLayout(new BoxLayout(north, BoxLayout.Y_AXIS));
            north.setOpaque(false);
            north.add(titleRow);
            north.add(Box.createVerticalStrut(12));
            toolbar.setAlignmentX(Component.LEFT_ALIGNMENT);
            titleRow.setAlignmentX(Component.LEFT_ALIGNMENT);
            north.add(toolbar);
            add(north, BorderLayout.NORTH);

            jTable.setRowHeight(26);
            jTable.setFillsViewportHeight(true);
            jTable.getTableHeader().setReorderingAllowed(false);
            jTable.setSelectionMode(ListSelectionModel.SINGLE_SELECTION);
            add(new JScrollPane(jTable), BorderLayout.CENTER);
            status.setForeground(Color.GRAY);
            add(status, BorderLayout.SOUTH);

            btnAdd.addActionListener(e -> openForm(false));
            btnEdit.addActionListener(e -> openForm(true));
            btnDelete.addActionListener(e -> deleteSelected());
            btnRefresh.addActionListener(e -> load(null));
            jTable.getSelectionModel().addListSelectionListener(e -> updateButtons());
            jTable.addMouseListener(new MouseAdapter() {
                @Override public void mouseClicked(MouseEvent e) {
                    if (e.getClickCount() == 2 && btnEdit.isEnabled()) openForm(true);
                }
            });
            jTable.addKeyListener(new KeyAdapter() {
                @Override public void keyPressed(KeyEvent e) {
                    if (e.getKeyCode() == KeyEvent.VK_DELETE && btnDelete.isEnabled()) deleteSelected();
                }
            });
            search.getDocument().addDocumentListener(new DocumentListener() {
                public void insertUpdate(DocumentEvent e) { applyFilter(); }
                public void removeUpdate(DocumentEvent e) { applyFilter(); }
                public void changedUpdate(DocumentEvent e) { applyFilter(); }
            });
            updateButtons();
        }

        private void setStatus(String msg, boolean isError) {
            status.setForeground(isError ? new Color(200, 30, 30) : Color.GRAY);
            status.setText(msg);
        }

        private void updateButtons() {
            boolean sel = jTable.getSelectedRow() >= 0;
            btnAdd.setEnabled(writable && meta != null);
            btnEdit.setEnabled(writable && sel);
            btnDelete.setEnabled(writable && sel);
        }

        private void applyFilter() {
            if (sorter == null) return;
            String t = search.getText().trim();
            sorter.setRowFilter(t.isEmpty() ? null : RowFilter.regexFilter("(?i)" + Pattern.quote(t)));
        }

        void load(String note) {
            setStatus("Loading...", false);
            Util.run(() -> new Object[]{
                    DatabaseManager.fetchTable(table, user, ownOnly),
                    DatabaseManager.getMeta(table, user.role, ownOnly)
            }, res -> {
                model = (DefaultTableModel) res[0];
                meta = (DatabaseManager.TableMeta) res[1];
                writable = !ownOnly || meta.ownerCol != null;
                jTable.setModel(model);
                sorter = new TableRowSorter<>(model);
                jTable.setRowSorter(sorter);
                applyFilter();
                for (int c = 0; c < model.getColumnCount(); c++) {
                    jTable.getColumnModel().getColumn(c).setHeaderValue(Util.pretty(model.getColumnName(c)));
                }
                fitColumns();
                updateButtons();
                String count = model.getRowCount() + " record(s)";
                String ro = (!writable && (canAdd || canEdit || canDelete))
                        ? "  \u2022  read-only (no owner column found)" : "";
                setStatus((note == null ? "" : note + "  \u2022  ") + count + ro, false);
            }, msg -> setStatus(msg, true));
        }

        private void fitColumns() {
            boolean few = jTable.getColumnCount() <= 7;
            jTable.setAutoResizeMode(few ? JTable.AUTO_RESIZE_ALL_COLUMNS : JTable.AUTO_RESIZE_OFF);
            if (few) return;
            for (int c = 0; c < jTable.getColumnCount(); c++) {
                TableColumn col = jTable.getColumnModel().getColumn(c);
                int w = jTable.getTableHeader().getDefaultRenderer()
                        .getTableCellRendererComponent(jTable, col.getHeaderValue(), false, false, 0, c)
                        .getPreferredSize().width;
                for (int r = 0; r < Math.min(jTable.getRowCount(), 50); r++) {
                    w = Math.max(w, jTable.prepareRenderer(jTable.getCellRenderer(r, c), r, c).getPreferredSize().width);
                }
                col.setPreferredWidth(Math.min(w + 24, 280));
            }
        }

        private Map<String, Object> selectedRow() {
            int v = jTable.getSelectedRow();
            if (v < 0 || model == null) return null;
            int m = jTable.convertRowIndexToModel(v);
            Map<String, Object> map = new LinkedHashMap<>();
            for (int c = 0; c < model.getColumnCount(); c++) map.put(model.getColumnName(c), model.getValueAt(m, c));
            return map;
        }

        private void openForm(boolean editing) {
            if (meta == null) return;
            Map<String, Object> existing = null;
            if (editing) {
                existing = selectedRow();
                if (existing == null) { setStatus("Select a row first.", true); return; }
            }
            try {
                RecordDialog d = new RecordDialog(SwingUtilities.getWindowAncestor(this),
                        (editing ? "Edit " : "Add ") + title, table, meta, user, existing,
                        () -> load(editing ? "Changes saved" : "Record added"));
                d.setVisible(true);
            } catch (Exception ex) {
                setStatus(ex.getMessage(), true);
            }
        }

        private void deleteSelected() {
            Map<String, Object> row = selectedRow();
            if (row == null || meta == null) return;
            int ans = JOptionPane.showConfirmDialog(this,
                    "Delete the selected record? This cannot be undone.",
                    "Confirm Delete", JOptionPane.YES_NO_OPTION, JOptionPane.WARNING_MESSAGE);
            if (ans != JOptionPane.YES_OPTION) return;

            Map<String, Object> where = new LinkedHashMap<>();
            for (DatabaseManager.ColInfo c : meta.cols) if (c.isPk) where.put(c.name, row.get(c.name));
            if (meta.ownerCol != null) where.put(meta.ownerCol, user.profileId);
            Util.run(() -> {
                DatabaseManager.deleteRow(table, where);
                return Boolean.TRUE;
            }, ok -> load("Record deleted"), msg -> setStatus(msg, true));
        }
    }
}


public class FoodShareApp extends JFrame {

    private final Color COLOR_NAVBAR = new Color(20, 40, 30);
    private final Color COLOR_GREEN_CARD = new Color(25, 135, 84);
    private final Color COLOR_DARK_CARD = new Color(33, 37, 41);
    private final Color COLOR_BG = new Color(245, 245, 245);

    private final CardLayout screens = new CardLayout();
    private final JPanel root = new JPanel(screens);
    private DashboardPanel dashboard;

    
    private JTextField txtLoginEmail;
    private JPasswordField txtLoginPass;

    
    private JTextField txtRegName, txtRegEmail, txtRegPhone, txtRegCity, txtRegAddress;
    private JPasswordField txtRegPass;
    private JComboBox<String> cbDonorType;

    public FoodShareApp() {
        setTitle("Food Share Portal");
        setSize(1100, 700);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLocationRelativeTo(null);

        root.add(buildAuthScreen(), "auth");
        add(root);
        screens.show(root, "auth");
    }

    
    private JPanel buildAuthScreen() {
        JPanel screen = new JPanel(new BorderLayout());

        JPanel navbar = new JPanel(new BorderLayout());
        navbar.setBackground(COLOR_NAVBAR);
        navbar.setPreferredSize(new Dimension(1000, 50));
        navbar.setBorder(new EmptyBorder(10, 20, 10, 20));

        JLabel brandLabel = new JLabel("\u2665 Food Share Portal");
        brandLabel.setForeground(new Color(40, 167, 69));
        brandLabel.setFont(new Font("Segoe UI", Font.BOLD, 18));
        navbar.add(brandLabel, BorderLayout.WEST);
        screen.add(navbar, BorderLayout.NORTH);

        JPanel mainPanel = new JPanel(new GridBagLayout());
        mainPanel.setBackground(COLOR_BG);
        mainPanel.setBorder(new EmptyBorder(30, 30, 30, 30));

        GridBagConstraints gbc = new GridBagConstraints();
        gbc.fill = GridBagConstraints.BOTH;
        gbc.insets = new Insets(10, 10, 10, 10);

        gbc.gridx = 0; gbc.gridy = 0; gbc.weightx = 0.4; gbc.weighty = 1.0;
        mainPanel.add(createLoginCard(), gbc);

        gbc.gridx = 1; gbc.gridy = 0; gbc.weightx = 0.6; gbc.weighty = 1.0;
        mainPanel.add(createRegisterCard(), gbc);

        screen.add(mainPanel, BorderLayout.CENTER);
        return screen;
    }

    private JLabel fieldLabel(String text) {
        JLabel l = new JLabel(text);
        l.setAlignmentX(Component.LEFT_ALIGNMENT);
        return l;
    }

    private JPanel createLoginCard() {
        JPanel card = new JPanel(new BorderLayout());
        card.setBackground(Color.WHITE);
        card.setBorder(BorderFactory.createLineBorder(new Color(220, 220, 220), 1));

        JPanel header = new JPanel(new FlowLayout(FlowLayout.LEFT, 15, 15));
        header.setBackground(COLOR_GREEN_CARD);
        JLabel lblHeader = new JLabel("Login");
        lblHeader.setForeground(Color.WHITE);
        lblHeader.setFont(new Font("Segoe UI", Font.BOLD, 18));
        header.add(lblHeader);
        card.add(header, BorderLayout.NORTH);

        JPanel body = new JPanel();
        body.setLayout(new BoxLayout(body, BoxLayout.Y_AXIS));
        body.setBackground(Color.WHITE);
        body.setBorder(new EmptyBorder(20, 20, 20, 20));

        body.add(fieldLabel("Email Address"));
        body.add(Box.createVerticalStrut(5));
        txtLoginEmail = new JTextField();
        txtLoginEmail.setMaximumSize(new Dimension(Integer.MAX_VALUE, 35));
        txtLoginEmail.setAlignmentX(Component.LEFT_ALIGNMENT);
        body.add(txtLoginEmail);
        body.add(Box.createVerticalStrut(15));

        body.add(fieldLabel("Password"));
        body.add(Box.createVerticalStrut(5));
        txtLoginPass = new JPasswordField();
        txtLoginPass.setMaximumSize(new Dimension(Integer.MAX_VALUE, 35));
        txtLoginPass.setAlignmentX(Component.LEFT_ALIGNMENT);
        txtLoginPass.addActionListener(e -> executeLoginAsync()); // Enter key logs in
        body.add(txtLoginPass);
        body.add(Box.createVerticalStrut(25));

        JButton btnLogin = new JButton("Log In");
        btnLogin.setBackground(COLOR_GREEN_CARD);
        btnLogin.setForeground(Color.WHITE);
        btnLogin.setFont(new Font("Segoe UI", Font.BOLD, 14));
        btnLogin.setMaximumSize(new Dimension(Integer.MAX_VALUE, 40));
        btnLogin.setAlignmentX(Component.LEFT_ALIGNMENT);
        btnLogin.addActionListener(e -> executeLoginAsync());

        body.add(btnLogin);
        card.add(body, BorderLayout.CENTER);
        return card;
    }

    private JPanel createRegisterCard() {
        JPanel card = new JPanel(new BorderLayout());
        card.setBackground(Color.WHITE);
        card.setBorder(BorderFactory.createLineBorder(new Color(220, 220, 220), 1));

        JPanel header = new JPanel(new FlowLayout(FlowLayout.LEFT, 15, 15));
        header.setBackground(COLOR_DARK_CARD);
        JLabel lblHeader = new JLabel("Register as Donor");
        lblHeader.setForeground(Color.WHITE);
        lblHeader.setFont(new Font("Segoe UI", Font.BOLD, 18));
        header.add(lblHeader);
        card.add(header, BorderLayout.NORTH);

        JPanel body = new JPanel(new GridLayout(8, 2, 10, 10));
        body.setBackground(Color.WHITE);
        body.setBorder(new EmptyBorder(20, 20, 20, 20));

        body.add(new JLabel("Full Name"));
        body.add(new JLabel("Email"));
        txtRegName = new JTextField();
        txtRegEmail = new JTextField();
        body.add(txtRegName);
        body.add(txtRegEmail);

        body.add(new JLabel("Phone Number"));
        body.add(new JLabel("Password"));
        txtRegPhone = new JTextField();
        txtRegPass = new JPasswordField();
        body.add(txtRegPhone);
        body.add(txtRegPass);

        body.add(new JLabel("Role"));
        body.add(new JLabel("Donor Type"));
        JComboBox<String> cbRole = new JComboBox<>(new String[]{"Donor"});
        cbRole.setEnabled(false);
        cbDonorType = new JComboBox<>(new String[]{"Individual", "Restaurant", "Hotel", "Corporate"});
        body.add(cbRole);
        body.add(cbDonorType);

        body.add(new JLabel("City"));
        body.add(new JLabel("Address"));
        txtRegCity = new JTextField();
        txtRegAddress = new JTextField();
        body.add(txtRegCity);
        body.add(txtRegAddress);

        JPanel footer = new JPanel(new BorderLayout());
        footer.setBackground(Color.WHITE);
        footer.setBorder(new EmptyBorder(0, 20, 20, 20));

        JButton btnRegister = new JButton("Create Donor Account");
        btnRegister.setBackground(COLOR_DARK_CARD);
        btnRegister.setForeground(Color.WHITE);
        btnRegister.setFont(new Font("Segoe UI", Font.BOLD, 14));
        btnRegister.setPreferredSize(new Dimension(500, 40));
        btnRegister.addActionListener(e -> executeRegisterAsync());

        footer.add(btnRegister, BorderLayout.CENTER);
        card.add(body, BorderLayout.CENTER);
        card.add(footer, BorderLayout.SOUTH);
        return card;
    }

    
    private void showDashboard(User user) {
        if (dashboard != null) root.remove(dashboard);
        dashboard = new DashboardPanel(user, this::logout);
        root.add(dashboard, "dash");
        root.revalidate();
        screens.show(root, "dash");
        setTitle("Food Share Portal - " + user.fullName + " (" + user.role + ")");
    }

    private void logout() {
        txtLoginPass.setText("");
        setTitle("Food Share Portal");
        screens.show(root, "auth");
    }

    
    private void executeLoginAsync() {
        String email = txtLoginEmail.getText();
        String password = new String(txtLoginPass.getPassword());

        Thread loginThread = new Thread(() -> {
            try {
                User user = DatabaseManager.authenticateUser(email, password);
                SwingUtilities.invokeLater(() -> {
                    if (user != null) {
                        showDashboard(user);
                    } else {
                        JOptionPane.showMessageDialog(this, "Invalid email or password.",
                                "Login Error", JOptionPane.ERROR_MESSAGE);
                    }
                });
            } catch (Exception ex) {
                SwingUtilities.invokeLater(() -> JOptionPane.showMessageDialog(this,
                        ex.getMessage(), "Database Error", JOptionPane.ERROR_MESSAGE));
            }
        });
        loginThread.start();
    }

    private void executeRegisterAsync() {
        String name = txtRegName.getText();
        String email = txtRegEmail.getText();
        String phone = txtRegPhone.getText();
        String password = new String(txtRegPass.getPassword());
        String donorType = (String) cbDonorType.getSelectedItem();
        String city = txtRegCity.getText();
        String address = txtRegAddress.getText();

        Donor donor = new Donor(0, name, email, donorType, city, address);

        Thread regThread = new Thread(() -> {
            try {
                boolean success = DatabaseManager.registerDonor(donor, phone, password);
                SwingUtilities.invokeLater(() -> {
                    if (success) {
                        JOptionPane.showMessageDialog(this,
                                "Donor account created successfully! You can now log in.",
                                "Registration Success", JOptionPane.INFORMATION_MESSAGE);
                        txtRegName.setText("");
                        txtRegEmail.setText("");
                        txtRegPhone.setText("");
                        txtRegPass.setText("");
                        txtRegCity.setText("");
                        txtRegAddress.setText("");
                    }
                });
            } catch (Exception ex) {
                SwingUtilities.invokeLater(() -> JOptionPane.showMessageDialog(this,
                        ex.getMessage(), "Registration Error", JOptionPane.ERROR_MESSAGE));
            }
        });
        regThread.start();
    }

    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> new FoodShareApp().setVisible(true));
    }
}