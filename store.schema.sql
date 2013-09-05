CREATE TABLE "Known_Hosts" (
    "ID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    "IP" INTEGER NOT NULL,
    "Port" INTEGER NOT NULL,
    "Timestamp" INTEGER NOT NULL
);
CREATE INDEX "IP" on Known_Hosts (IP ASC);
CREATE TABLE "Inventory" (
    "ID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    "Hash" BLOB NOT NULL,
    "Timestamp" INTEGER NOT NULL
);
CREATE INDEX "Hash" on Inventory (Hash ASC);
