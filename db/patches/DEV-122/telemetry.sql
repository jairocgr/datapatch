
CREATE TABLE telemetry (
  id integer not null auto_increment primary key,
  action varchar(24) not null,
  created_at timestamp not null
);

INSERT INTO telemetry VALUES
  (101, 'action101', '2018-01-01 13:30:35'),
  (102, 'action102', '2018-02-03 13:30:35');
