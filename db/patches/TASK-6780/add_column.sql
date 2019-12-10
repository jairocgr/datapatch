--
-- @runas pap
-- @after pap
--

ALTER TABLE task6280pap
  ADD COLUMN created_at timestamp not null AFTER `action`;
