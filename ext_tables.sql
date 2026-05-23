# This is an internal table, no TCA
CREATE TABLE tx_webp_failed
(
    uid                int(11) NOT NULL auto_increment,
    file_id            INT(11) NOT NULL DEFAULT '0',
    configuration      TEXT,
    configuration_hash VARCHAR(32),
    format             VARCHAR(8) NOT NULL DEFAULT 'webp',
    PRIMARY KEY (uid),
    KEY configuration (file_id, configuration_hash, format)
);

CREATE TABLE tx_webp_queue
(
    uid                int(11)      NOT NULL auto_increment,
    original_file_id   INT(11)      NOT NULL,
    processed_file_id  INT(11)      NOT NULL DEFAULT 0,
    task_type          VARCHAR(255) NOT NULL DEFAULT '',
    configuration      TEXT,
    configuration_hash VARCHAR(32)  NOT NULL,
    enqueued_at        INT(11)      NOT NULL DEFAULT 0,
    format             VARCHAR(8)   NOT NULL DEFAULT 'webp',
    PRIMARY KEY (uid),
    UNIQUE KEY queue_dedup (original_file_id, processed_file_id, task_type, configuration_hash, format),
    KEY enqueued_at (enqueued_at)
);

# Per-storage sibling mode override.
# 0 = Auto (Local: on, others: off), 1 = Enabled, 2 = Disabled
CREATE TABLE sys_file_storage
(
    tx_webp_mode smallint(5) unsigned DEFAULT '0' NOT NULL
);
