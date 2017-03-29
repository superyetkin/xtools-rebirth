CREATE TABLE `wiki` (
    `dbname` varchar(32) NOT NULL PRIMARY KEY,
    `lang` varchar(12) NOT NULL DEFAULT 'en',
    `name` text,
    `family` text,
    `url` text
);
