<?php declare(strict_types=1);

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 **/

// Generate git repository statistics
// Usage: php build.php
// Requirements:
//   - git (tested on version 2.34.1)
// Output:
//
//   # README
//
//   ## Stress Level 30%
//
//       Weekday  Date        Commits Files Lines Level
//       Sunday   2026-03-15        1     2    10 30% XXX
//       Monday   2026-03-14        2    12    20 20% XX
//       Friday   2026-03-13        3     3    25 50% XXXXX
//
//       Month     Commits Files Lines Level
//       March           1     2    10 50% XXXXX
//       February        2    12    20 30% XXX
//       January         4     3    25 20% XX
//
// Notes:
//  * if 2 consecutive commits change the same file ,
//    we counted as 2 files changed
//  * if a commit insert 1 line then the next commit delete the line ,
//    we counted as 2 lines changed

$GIT_DIR = getenv('GIT_DIR');
if (empty($GIT_DIR) == true) {
  fwrite(STDERR, "ERROR environment GIT_DIR is not set.\n"); exit(1);
}

$file  = __DIR__.DIRECTORY_SEPARATOR.'README.md';
$text  = 'Religious Affairs Minister Zulkifli Hasan claiming in parliament'
  .' that work stress turns people gay. Published: 1:37 pm, 28 Jan 2026 '
  .'[South China Morning Post](https://www.scmp.com/week-asia/politics/article'
  .'/3341504/malaysians-lampoon-minister-over-stress-gay'
  .'-link-they-never-work-hard-parliament)'
  ;

$day_count = 4; // number of days printed
$month_count = 3; // number of months printed

$handler = new \GitHandler;
$handler->command = 'git --git-dir='.$GIT_DIR;

$date_start = 'first day of this month -'.$month_count.' months';
$date_stop  = 'this day';
$commits = get_commits_by_date($handler, $date_start, $date_stop);
$stat = get_stat_summary($handler, $commits);

$today_level = '';

$t = new Table;
$row = $t->addRow();
$row->addCells('Weekday', 'Date', 'Commits', 'Files', 'Lines', 'Level');
$time = time();
for ($i = 0; $i < $day_count; $i++) {
  $date = date('Y-m-d', $time);
  echo($date."\n");
  if (array_key_exists($date, $stat->dates) == false) {
    $row = $t->addRow();
    $row->addCell(date('l', $time));
    $row->addCell(date('Y-m-d', $time));
    $row->addCell(0);
    $row->addCell(0);
    $row->addCell(0);
    $level_text = '  0%';
    $row->addCell($level_text);
    if ($i == 0) { $today_level = $level_text; }
    $time = $time - 24 * 60 * 60;
    continue;
  }
  $stat_date = $stat->dates[$date];
  $row = $t->addRow();
  $row->addCell($stat_date->weekday);
  $row->addCell($stat_date->date);
  $row->addCell($stat_date->commit_count);
  $row->addCell($stat_date->file_count);
  $row->addCell($stat_date->line_count);
  $level = ($stat_date->line_count * 100) / $stat->avg_line_per_day;
  if ($level < 10) {
    $level_text = '  '.number_format($level).'%';
  } elseif ($level < 100) {
    $level_text = ' '.number_format($level).'%';
  } else {
    $level_text = number_format($level).'%';
  }
  if ($i == 0) { $today_level = $level_text; }
  $row->addCell($level_text);
  $time = $time - 24 * 60 * 60;
}

$date_text = $t->save();
$text .= "\n\n# Stress Level ".$today_level;

$t = new Table;
$row = $t->addRow();
$row->addCells('Month', 'Commits', 'Files', 'Lines', 'Level');
$time = time();
for ($i = 0; $i < $month_count; $i++) {
  $month = date('Y-m', $time);
  if (array_key_exists($month, $stat->months) == false) {
    $row = $t->addRow();
    $row->addCell(date('F', $time));
    $row->addCell(0);
    $row->addCell(0);
    $row->addCell(0);
    $level_text = '  0%';
    $row->addCell($level_text);
    $time = strtotime('-1 month', $time);
    continue;
  }
  $stat_month = $stat->months[$month];
  $row = $t->addRow();
  $row->addCell($stat_month->month);
  $row->addCell($stat_month->commit_count);
  $row->addCell($stat_month->file_count);
  $row->addCell($stat_month->line_count);
  $level = ($stat_month->line_count * 100)
    / ($stat->line_count);
  if ($level < 10) {
    $level_text = '  '.number_format($level).'%';
  } elseif ($level < 100) {
    $level_text = ' '.number_format($level).'%';
  } else {
    $level_text = number_format($level).'%';
  }
  $row->addCell($level_text);
  $time = strtotime('-1 month', $time);
}

$month_text = $t->save();

$text .= "\n\n".$date_text."\n".$month_text;
$text .= "\n";

file_put_contents($file, $text);

// -----------------------------------------------------------------------------

class ArrayValues extends \ArrayObject {

  public string $_type;

  public function __construct() {
    parent::__construct();
  }

  public function append(mixed $value) :void {
    $type = gettype($value);
    if ($type !== $this->_type) {
      throw new \Exception("Not a ".$this->_type);
    }
    parent::append($value);
  }
}

class ArrayString extends \ArrayValues {

  public function __construct() {
    parent::__construct();
    $this->_type = 'string';
  }
}

class Table {

  // @var Array<TableColumn> $columns
  public $columns;
  // @var Array<TableRow> $rows
  public $rows;

  public string $row_prefix;
  public string $row_separator;
  public string $cell_separator;

  public function __construct() {
    $this->columns = array();
    $this->rows = array();
    $this->row_prefix = '    ';
    $this->row_separator = "\n";
    $this->cell_separator = ' ';
  }

  public function addRow() :TableRow {
    $row = new TableRow;
    array_push($this->rows, $row);
    return $row;
  }

  public function save() :string {

    // get column_size

    $column_size = array();

    $row_index = 0;
    foreach ($this->rows as $row) {
      $column_index = 0;
      foreach ($row->cells as $cell) {
        if ($row_index == 0) {
          $column_size[$column_index] = 0;
        }
        $cell_size = strlen( $cell->getText() );
        if ($cell_size > $column_size[$column_index]) {
          $column_size[$column_index] = $cell_size;
        }
        $column_index++;
      }
      $row_index++;
    }

    $s = '';
    $row_index = 0;
    foreach ($this->rows as $row) {
      $column_index = 0;
      foreach ($row->cells as $cell) {
        if ($column_index == 0) {
          $s .= $this->row_prefix;
        } else {
          $s .= $this->cell_separator;
        }
        $s .= $cell->getText($column_size[$column_index]);
        $column_index++;
      }
      $s .= $this->row_separator;
      $row_index++;
    }

    return $s;
  }
}

class TableColumn {
  public string $type;
}

class TableRow {

  // @var Array<TableCell> $cells
  public array $cells;

  public function __construct() {
    $this->cells = array();
  }

  public function addCell(mixed $value) :TableCell {
    $cell = new TableCell;
    $cell->value = $value;
    array_push($this->cells, $cell);
    return $cell;
  }

  public function addCells() :void {
    foreach (func_get_args() as $value) {
      $this->addCell($value);
    }
  }

}

class TableCell {

  public string $type = 'TEXT';
  public mixed $value;

  function getText(int $size = 0) :string {
    $type = gettype($this->value);
    if ($type == 'string') {
      $text = $this->value;
      if ($size == 0) { return $text; }
      return str_pad($text, $size, ' ', STR_PAD_RIGHT);
    } else if ($type == 'integer') {
      $text = number_format($this->value);
      if ($size == 0) { return $text; }
      return str_pad($text, $size, ' ', STR_PAD_LEFT);
    }
    throw new \Exception("Invalid type. ".$type);
  }
}

class GitHandler {
  public string $command;
}

class CommitStat {
  public int $time = 0;
  public int $commit_count = 0;
  public int $file_count = 0;
  public int $line_count = 0;
}

class CommitDayStat {
  public string $weekday;
  public string $date;
  public int $commit_count = 0;
  public int $file_count = 0;
  public int $line_count = 0;
  public string $level = '';
}

class CommitMonthStat {
  public string $month;
  public int $day_count = 0;
  public int $commit_count = 0;
  public int $file_count = 0;
  public int $line_count = 0;
  public string $level = '';
}

class CommitSummaryStat {
  // @var Array<CommitDayStat> $dates
  public array $dates;
  // @var Array<CommitMonthStat> $months
  public array $months;
  public int $day_count = 0;
  public int $commit_count = 0;
  public int $file_count = 0;
  public int $line_count = 0;
  public float $avg_line_per_day = 0;
}

function get_commits_by_date(
    GitHandler $handler
  , string $time_start
  , string $time_stop
  ) :ArrayString {

  $time_before = strtotime($time_stop);
  $date_before = date('Y-m-d', $time_before);
  $time_after = strtotime($time_start);
  $date_after = date('Y-m-d', $time_after);
  // $command = $handler->command.' log'
  //   .' --after='.$date_after.' --before='.$date_before
  //   .' "--format=%H %cd" --date=short';
  // echo($command."\n");
  // passthru($command);
  $command = $handler->command.' log'
    .' --after='.$date_after.' --before='.$date_before
    .' --format=%H';
  exec($command, $lines, $result);
  if ($result != 0) {
    throw new \Exception("Non zero exit code. ".$command);
  }

  $commits = new \ArrayString;
  foreach ($lines as $line) {
    $commits->append($line);
  }

  return $commits;
}

function get_days_count(string $date_start, string $date_stop) :int {
  $time_start  = strtotime($date_start);
  $time_stop = strtotime($date_stop);
  return ($time_stop - $time_start) / (24 * 60 * 60);
}

function get_stat_summary(
    GitHandler $handler
  , $commits
  ) :CommitSummaryStat {

  $summary = new \CommitSummaryStat;

  $date_commits = array();
  $month_commits = array();

  foreach ($commits as $commit) {

    $stat = get_commit_stat($handler, $commit);
    $date = date('Y-m-d', $stat->time);
    $month = date('Y-m', $stat->time);

    if (array_key_exists($date, $date_commits) == false) {
      $date_commit = new \CommitDayStat;
      $date_commit->weekday = date('l', $stat->time);
      $date_commit->date = date('Y-m-d', $stat->time);
      $date_commits[$date] = $date_commit;
    } else {
      $date_commit = $date_commits[$date];
    }
    $date_commit->commit_count++;
    $date_commit->file_count += $stat->file_count;
    $date_commit->line_count += $stat->line_count;

    if (array_key_exists($month, $month_commits) == false) {
      $month_commit = new \CommitMonthStat;
      $month_commit->month = date('F', $stat->time);
      $month_time_start = strtotime('first day of this month', $stat->time);
      if (date('Y-m') == date('Y-m', $stat->time)) {
        // the same month as today
        $month_time_stop = strtotime('this day', $stat->time);
      } else {
        $month_time_stop = strtotime('+1 month', $month_time_start);
      }
      $month_day_count = ($month_time_stop - $month_time_start) / (24 * 60 * 60);
      // echo(
      //      date('Y-m-d', $stat->time)
      // ." ".date('Y-m-d', $month_time_start)
      // ." ".date('Y-m-d', $month_time_stop)
      // ." ".number_format($month_day_count)
      // ."\n");
      $month_commit->day_count = $month_day_count;
      $summary->day_count += $month_day_count;
      $month_commits[$month] = $month_commit;
    } else {
      $month_commit = $month_commits[$month];
    }
    $month_commit->commit_count++;
    $month_commit->file_count += $stat->file_count;
    $month_commit->line_count += $stat->line_count;

    $summary->commit_count++;
    $summary->file_count += $stat->file_count;
    $summary->line_count += $stat->line_count;
  }

  $summary->dates = $date_commits;
  $summary->months = $month_commits;
  $summary->avg_line_per_day = $summary->line_count / $summary->day_count;

  return $summary;
}

// Get commit statistics

function get_commit_stat(GitHandler $handler, string $commit) :CommitStat {
  $stat = new CommitStat;
  $command = $handler->command.' log'
    .' -n 1 --stat '.$commit;
  exec($command, $lines, $result);
  if ($result != 0) {
    throw new \Exception("Non zero exit code. ".$command);
  }
  $text = implode("\n", $lines);
  if (preg_match("/Date:\s+([^\\n]+)/", $text, $match) == true) {
    $stat->time = strtotime($match[1]);
  }
  if (preg_match("/([0-9]+) file(s)? changed/", $text, $match) == true) {
    $stat->file_count = intval($match[1]);
  }
  if (preg_match("/([0-9]+) insertion/", $text, $match) == true) {
    $stat->line_count += intval($match[1]);
  }
  if (preg_match("/([0-9]+) deletion/", $text, $match) == true) {
    $stat->line_count += intval($match[1]);
  }
  return $stat;
}

