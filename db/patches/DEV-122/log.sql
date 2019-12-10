--
-- DEV-122/log.sql
--
-- @databases logs
-- @servers mysql57
--

CREATE TABLE dev122log (
  id integer not null auto_increment primary key,
  action varchar(24) not null,
  created_at timestamp not null
);

INSERT INTO dev122log VALUES
  (101, 'action101', '2018-01-01 13:30:35'),
  (102, 'action102', '2018-02-03 13:30:35'),
  (105, 'action105', '2018-04-17 13:30:35');
