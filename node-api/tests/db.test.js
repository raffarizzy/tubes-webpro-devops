jest.mock("mysql2/promise", () => ({
  createPool: jest.fn().mockReturnValue({
    on: jest.fn((event, cb) => {
      if (event === "connection") {
        cb({ query: jest.fn() });
      }
    }),
  }),
}));

describe("Database Configuration", () => {
  const originalEnv = process.env;

  beforeEach(() => {
    jest.resetModules();
    process.env = { ...originalEnv };
  });

  afterAll(() => {
    process.env = originalEnv;
  });

  test("menginisialisasi pool dengan env default", () => {
    delete process.env.DB_HOST;
    delete process.env.DB_PORT;
    delete process.env.DB_USER;
    delete process.env.DB_USERNAME;
    delete process.env.DB_PASS;
    delete process.env.DB_PASSWORD;
    delete process.env.DB_NAME;
    delete process.env.DB_DATABASE;

    const db = require("../src/config/db");
    expect(db).toBeDefined();
    expect(db.on).toHaveBeenCalledWith("connection", expect.any(Function));
  });

  test("menggunakan env variabel alternatif (DB_USERNAME, DB_DATABASE, DB_PASSWORD)", () => {
    process.env.DB_USERNAME = "custom_user";
    process.env.DB_DATABASE = "custom_db";
    process.env.DB_PASSWORD = "custom_password";
    process.env.DB_PORT = "3307";

    const db = require("../src/config/db");
    expect(db).toBeDefined();
  });
});

