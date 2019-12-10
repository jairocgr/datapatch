--
-- DEV-231/log.sql
--
-- @databases logs
-- @servers mysql57
-- @after zun
--

CREATE TABLE dev231log (
  id integer not null auto_increment primary key,
  action varchar(24) not null
);
