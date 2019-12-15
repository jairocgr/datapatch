
CREATE TABLE tenants (
  id integer not null auto_increment primary key,
  handle varchar(24) not null,
  name varchar(128) not null,
  title varchar(160)
);

CREATE TABLE users (
  id integer not null auto_increment primary key,
  login varchar(24) not null,
  active boolean not null default true,
  phone varchar(32) not null,
  password varchar(40),
  tenant_id integer not null,

  CONSTRAINT fk_user_tenant FOREIGN KEY (tenant_id)
    REFERENCES tenants(id) ON UPDATE CASCADE
);

ALTER TABLE users ADD
  INDEX user_login_index (login);

CREATE TABLE hash (
  id integer not null auto_increment primary key,
  k varchar(24) not null,
  value varchar(32)
);

INSERT INTO tenants (id, handle, name) VALUES
  (1, 'tenant01', 'Test Tenant 01 Çñ'),
  (2, 'tenant02', 'Test Tenant 02 Çñ'),
  (3, 'tenant03', 'Test Tenant 03 Çñ'),
  (4, 'tenant04', 'Test Tenant 04 Çñ');

INSERT INTO users VALUES
  (101, 'usr101', true, '+55 67 99168-2101', sha1('testpw'), 1),
  (102, 'usr102', true, '+55 67 99168-2102', sha1('testpw'), 1),
  (103, 'usr103', true, '+55 67 99168-2103', sha1('testpw'), 1),

  (201, 'usr201', false, '+55 67 99162-2201', sha1('testpw'), 2),
  (202, 'usr202', true,  '+55 67 99162-2202', sha1('testpw'), 2),
  (203, 'usr203', false, '+55 67 99162-2203', sha1('testpw'), 2),
  (204, 'usr204', true,  '+55 67 99162-2204', sha1('testpw'), 2),

  (301, 'usr301', false, '+55 67 99368-2301', sha1('testpw'), 3),
  (302, 'usr302', true,  '+55 67 99368-2302', sha1('testpw'), 3),
  (303, 'usr303', false, '+55 67 99368-2303', sha1('testpw'), 3),
  (304, 'usr304', true,  '+55 67 99368-2304', sha1('testpw'), 3);

/*!80016 alter user 'root'@'%' identified with mysql_native_password by 'datapatch' */;
