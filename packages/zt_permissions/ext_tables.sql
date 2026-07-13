CREATE TABLE tx_zt_audit_log (
    uid int(11) NOT NULL auto_increment,
    user_id int(11) DEFAULT 0 NOT NULL,
    tstamp int(11) DEFAULT 0 NOT NULL,
    ip_hash char(64) DEFAULT '' NOT NULL,
    action_type varchar(64) DEFAULT '' NOT NULL,
    risk_level varchar(16) DEFAULT '' NOT NULL,
    rule_id varchar(64) DEFAULT '' NOT NULL,
    rule_description varchar(255) DEFAULT '' NOT NULL,
    page_context varchar(255) DEFAULT '' NOT NULL,
    PRIMARY KEY (uid),
    KEY tstamp (tstamp)
);