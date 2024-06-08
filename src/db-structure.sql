CREATE TABLE IF NOT EXISTS `message` (
    `chat_id`           bigint,                       -- 'Unique chat identifier',
    `id`                bigint UNSIGNED,              -- 'Unique message identifier',
    `message_thread_id` bigint(20)      DEFAULT NULL, -- 'Unique identifier of a message thread to which the message belongs; for supergroups only',
    `user_id`           bigint    NULL,               -- 'Unique user identifier',
    `date`              timestamp NULL  DEFAULT NULL, -- 'Date the message was sent in timestamp format',
    `reply_to_message`  bigint UNSIGNED DEFAULT NULL, -- 'Message that this message is reply to',
    `username`          varchar,                       -- 'Message that this message is reply to',
    `message_text` varchar --Message text
);
CREATE INDEX IF NOT EXISTS idx_chat_id_id ON message (chat_id, id);
CREATE INDEX IF NOT EXISTS idx_date ON message (date);
CREATE INDEX IF NOT EXISTS idx_user ON message (user_id);
CREATE TABLE IF NOT EXISTS `user_preferences`
(
    `user_id` bigint PRIMARY KEY,
    `preferences` text --stored as JSON
)
