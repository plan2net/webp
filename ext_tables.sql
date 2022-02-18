# This is an internal table, no TCA
CREATE TABLE tx_webp_failed
(
    uid                int(11) NOT NULL auto_increment,
    file_id            INT(11) NOT NULL DEFAULT '0',
    configuration      TEXT,
    configuration_hash VARCHAR(32),
    PRIMARY KEY (uid),
    KEY configuration (file_id, configuration_hash)
);