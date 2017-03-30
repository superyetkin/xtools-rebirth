--
-- This is the table required for the multi-wiki setup of xTools.
--
CREATE TABLE `wiki` (
    `dbname` varchar(32) NOT NULL PRIMARY KEY,
    `lang` varchar(12) NOT NULL DEFAULT 'en',
    `name` text,
    `family` text,
    `url` text
);
