--
-- DEV-122/pap.sql
--
-- @databases zun_*
-- @servers mysql5*, local
-- @after zun, reports, log
--

CREATE TABLE dev122pap (
  id integer not null auto_increment primary key,
  action varchar(24) not null,
  created_at timestamp not null
);

INSERT INTO dev122pap VALUES
  (101, 'action101', '2018-01-01 13:30:35'),
  (102, 'action102', '2018-02-03 13:30:35'),
  (103, 'action103', '2018-03-21 13:30:35'),
  (104, 'action104', '2018-04-01 13:30:35'),
  (105, 'action105', '2018-04-17 13:30:35');
