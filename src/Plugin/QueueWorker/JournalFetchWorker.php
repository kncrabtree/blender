<?php

namespace Drupal\blender\Plugin\QueueWorker;

/**
 * A journal article fetcher.
 *
 * @QueueWorker(
 *   id = "journal_fetcher",
 *   title = @Translation("Journal Fetcher"),
 *   cron = {"time" = 20}
 * )
 */
class JournalFetchWorker extends JournalFetchBase {}

?>
