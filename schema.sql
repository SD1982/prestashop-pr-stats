DROP TABLE IF EXISTS `event`;
CREATE TABLE IF NOT EXISTS `event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `date` timestamp NULL DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `label`;
CREATE TABLE IF NOT EXISTS `label` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `pr`;
CREATE TABLE IF NOT EXISTS `pr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `created` timestamp NULL DEFAULT NULL,
  `merged` timestamp NULL DEFAULT NULL,
  `time_before_first_wfqa` int(11) DEFAULT NULL,
  `times_of_wfqa_labelling` int(11) DEFAULT NULL,
  `total_time_as_wfqa` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
COMMIT;
