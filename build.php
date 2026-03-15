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
// Usage: php build.php <GIT-WORK-TREE>
// Requirements:
//   - git (tested on version 2.34.1)
// Output:
//
//   # README
//
//   ## Stress Level 30%
//
//   Number of file changed, line insertions and deletions:
//
//       Weekday  Date        Files Lines Level
//       Sunday   2026-03-15      2    10 30% XXX
//       Monday   2026-03-14     12    20 20% XX
//       Friday   2026-03-13      3    25 50% XXXXX
//
//       Month     Files Lines Level
//       March         2    10 50% XXXXX
//       February     12    20 30% XXX
//       January       3    25 20% XX

// Output file

$file = __DIR__.DIRECTORY_SEPARATOR.'README.md';
$text  = '# README';
$text .= "\n\n## Stress Level";

file_put_contents($file, $text);

