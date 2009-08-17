#
# Table structure for table 'tx_dataquery_queries'
#
CREATE TABLE tx_dataquery_queries (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	t3ver_oid int(11) DEFAULT '0' NOT NULL,
	t3ver_id int(11) DEFAULT '0' NOT NULL,
	t3ver_wsid int(11) DEFAULT '0' NOT NULL,
	t3ver_label varchar(30) DEFAULT '' NOT NULL,
	t3ver_state tinyint(4) DEFAULT '0' NOT NULL,
	t3ver_stage tinyint(4) DEFAULT '0' NOT NULL,
	t3ver_count int(11) DEFAULT '0' NOT NULL,
	t3ver_tstamp int(11) DEFAULT '0' NOT NULL,
	t3_origuid int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	title varchar(255) DEFAULT '' NOT NULL,
	description text NOT NULL,
	sql_query text NOT NULL,
	cache_duration int(11) DEFAULT '86400' NOT NULL,
	ignore_enable_fields tinyint(4) DEFAULT '0' NOT NULL,
	ignore_language_handling tinyint(4) DEFAULT '0' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY t3ver_oid (t3ver_oid,t3ver_wsid)
);

CREATE TABLE tx_dataquery_cache (
	query_id int(11) DEFAULT '0' NOT NULL,
	page_id int(11) DEFAULT '0' NOT NULL,
	cache_hash varchar(32) DEFAULT '' NOT NULL,
	expires int(11) DEFAULT '0' NOT NULL,
	structure_cache mediumtext NOT NULL
	KEY query (query_id,page_id),
) ENGINE = InnoDB;
