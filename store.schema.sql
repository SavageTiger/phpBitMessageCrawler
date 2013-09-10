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
    "Host" INTEGER NOT NULL,
    "InStore" INTEGER DEFAULT '0',
    "Timestamp" INTEGER NOT NULL
);
CREATE INDEX "Hash" on Inventory (Hash ASC);
CREATE TABLE "MessageStore" (
    "ID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    "Binary" BLOB NOT NULL,
    "Inventory" INTEGER NOT NULL,
    "Timestamp" INTEGER NOT NULL
);
CREATE TABLE "KeyStore" (
    "ID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    "Binary" BLOB NOT NULL,
    "Inventory" INTEGER NOT NULL,
    "Timestamp" INTEGER NOT NULL
);
