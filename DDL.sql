CREATE TABLE account
(
    uid INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    phone VARCHAR(13),
    address_id INT(11),
    wid VARCHAR(30),
    user_name VARCHAR(10) NOT NULL,
    sex TINYINT(4),
    user_icon VARCHAR(500),
    passwords_md5 VARCHAR(32)
);
CREATE UNIQUE INDEX account_phone_pk ON account (phone);
CREATE UNIQUE INDEX account_uid_uindex ON account (uid);
CREATE UNIQUE INDEX account_wid_pk ON account (wid);
CREATE TABLE address
(
    address_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_name VARCHAR(10),
    user_phone VARCHAR(13),
    user_address VARCHAR(100),
    uid INT(11)
);
CREATE UNIQUE INDEX address_address_id_uindex ON address (address_id);
CREATE INDEX address_uid_index ON address (uid);
CREATE TABLE deliver_goods_order
(
    order_id VARCHAR(20) PRIMARY KEY NOT NULL,
    order_type TINYINT(4) DEFAULT '0' NOT NULL,
    uid INT(11) NOT NULL,
    shop_id INT(11) NOT NULL,
    phase_id VARCHAR(20),
    goods_id INT(11),
    phase_record_id VARCHAR(30),
    lucky_time DATETIME,
    user_remarks VARCHAR(100),
    user_address VARCHAR(100),
    user_name VARCHAR(10),
    user_phone VARCHAR(13),
    goods_icon VARCHAR(300),
    goods_name VARCHAR(50),
    goods_phase INT(11),
    goods_money INT(11),
    user_buy_money INT(11),
    send_good_order VARCHAR(100),
    send_good_company VARCHAR(100),
    send_good_time DATETIME,
    order_data_time DATETIME
);
CREATE UNIQUE INDEX deliver_goods_order_order_id_uindex ON deliver_goods_order (order_id);
CREATE INDEX deliver_goods_order_shop_id_index ON deliver_goods_order (shop_id);
CREATE INDEX deliver_goods_order_uid_index ON deliver_goods_order (uid);
CREATE TABLE goods
(
    goods_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    goods_icons VARCHAR(1000),
    is_audit_through TINYINT(4) DEFAULT '1',
    goods_now_phase INT(11) DEFAULT '0',
    goods_now_phase_id VARCHAR(20),
    goods_name VARCHAR(50),
    goods_desc VARCHAR(100),
    shop_id INT(11),
    goods_money INT(11),
    left_phase INT(11),
    create_time DATETIME,
    is_show TINYINT(4) DEFAULT '1'
);
CREATE UNIQUE INDEX goods_goods_id_uindex ON goods (goods_id);
CREATE UNIQUE INDEX goods_goods_now_phase_id_uindex ON goods (goods_now_phase_id);
CREATE INDEX goods_shop_id_index ON goods (shop_id);
CREATE TABLE goods_phase
(
    phase_id VARCHAR(20) PRIMARY KEY NOT NULL,
    goods_id INT(11),
    phase INT(11),
    shop_id INT(11),
    need_money INT(11),
    now_money INT(11),
    winer_uid INT(11),
    lucky_time DATETIME,
    lucky_coupon INT(11),
    winer_address VARCHAR(15),
    winer_ip VARCHAR(15),
    winer_buy_count INT(11),
    lucky_order_id VARCHAR(20),
    winer_name VARCHAR(10),
    winer_icon VARCHAR(500)
);
CREATE INDEX goods_phase_goods_id_index ON goods_phase (goods_id);
CREATE UNIQUE INDEX goods_phase_phase_id_uindex ON goods_phase (phase_id);
CREATE INDEX goods_phase_phase_index ON goods_phase (phase);
CREATE TABLE manager
(
    manager_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    manager_name VARCHAR(100),
    manager_key VARCHAR(100)
);
CREATE UNIQUE INDEX manager_manager_id_uindex ON manager (manager_id);
CREATE INDEX manager_name_index ON manager (manager_name);
CREATE TABLE order_form
(
    order_id VARCHAR(20) PRIMARY KEY NOT NULL,
    shop_id INT(11),
    goods_id INT(11),
    phase_id VARCHAR(20),
    money INT(11),
    uid INT(11),
    order_time INT(11) NOT NULL,
    user_name VARCHAR(10),
    ip VARCHAR(15),
    ip_address VARCHAR(15),
    user_icon VARCHAR(500),
    is_pay TINYINT(4),
    order_data_time DATETIME
);
CREATE INDEX order_form_goods_id_index ON order_form (goods_id);
CREATE UNIQUE INDEX order_form_order_id_uindex ON order_form (order_id);
CREATE INDEX order_form_phase_id_index ON order_form (phase_id);
CREATE INDEX order_form_shop_id_index ON order_form (shop_id);
CREATE INDEX order_form_uid_index ON order_form (uid);
CREATE TABLE phase_record
(
    phase_record_id VARCHAR(30) PRIMARY KEY NOT NULL,
    uid INT(11) NOT NULL,
    phase_id VARCHAR(20) NOT NULL,
    pay_total_coupon INT(11) DEFAULT '0',
    type TINYINT(4) DEFAULT '0'
);
CREATE UNIQUE INDEX phase_record_id_uindex ON phase_record (phase_record_id);
CREATE INDEX phase_record_phase_id_index ON phase_record (phase_id);
CREATE INDEX phase_record_uid_index ON phase_record (uid);
CREATE TABLE shop
(
    shop_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    shop_desc VARCHAR(100) NOT NULL,
    wid VARCHAR(30) NOT NULL,
    shop_icon VARCHAR(500),
    shop_name VARCHAR(20) NOT NULL,
    create_time DATETIME,
    shop_qq VARCHAR(20),
    shop_phone VARCHAR(13),
    shop_address VARCHAR(200),
    shop_type VARCHAR(10),
    shop_wxname VARCHAR(30),
    is_show TINYINT(4) DEFAULT '1'
);
CREATE UNIQUE INDEX shop_shop_id_uindex ON shop (shop_id);
CREATE INDEX shop_type_index ON shop (shop_type);