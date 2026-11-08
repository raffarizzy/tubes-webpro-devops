const mysql = require("mysql2/promise");

const dbHost = process.env.DB_HOST || "localhost";
const dbPort = Number(process.env.DB_PORT) || 3306;
const dbUser = process.env.DB_USER || process.env.DB_USERNAME || "root";
const dbPass = process.env.DB_PASS !== undefined ? process.env.DB_PASS : (process.env.DB_PASSWORD !== undefined ? process.env.DB_PASSWORD : "");
const dbName = process.env.DB_NAME || process.env.DB_DATABASE || "tubes_devops";

const db = mysql.createPool({
    host: dbHost,
    port: dbPort,
    user: dbUser,
    password: dbPass,
    database: dbName,
    timezone: "+07:00",
});

db.on("connection", (connection) => {
    connection.query("SET time_zone = '+07:00'");
});

console.log(`DB CONNECTED TO: ${dbName} (user: ${dbUser}, host: ${dbHost}:${dbPort})`);

module.exports = db;
