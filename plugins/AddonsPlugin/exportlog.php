<?php
/**
 * AddonsPlugin for phplist.
 *
 * This file is a part of AddonsPlugin.
 *
 * @category  phplist
 *
 * @author    Duncan Cameron
 * @copyright 2011-2024 Duncan Cameron
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */

namespace phpList\plugin\AddonsPlugin;

use phpList\plugin\Common\ExportCSV;
use phpList\plugin\Common\IExportable;

class LogExporter implements IExportable
{
    private $limit;

    public function __construct($limit)
    {
        $this->limit = $limit;
    }

    public function exportFileName()
    {
        return 'eventlog';
    }

    public function exportRows()
    {
        global $tables;

        $sql =
            "SELECT *
            FROM(
                SELECT id, entered, page, entry
                FROM {$tables['eventlog']}
                ORDER BY id DESC
                LIMIT $this->limit
            ) AS t
            ORDER BY id ASC";
        $result = Sql_Query($sql);

        return $result;
    }

    public function exportFieldNames()
    {
        return ['id', 'entered', 'page', 'entry'];
    }

    public function exportValues(array $row)
    {
        return $row;
    }
}

if (isset($_GET['limit'])) {
    $limit = ctype_digit($_GET['limit']) ? $_GET['limit'] : 5000;
    $exporter = new ExportCSV(new LogExporter($limit));
    $exporter->export();

    exit;
}
echo <<<END
<form method="GET">
<p>Number of rows to export</p>
<input name="limit" value="5000"/>
<input type="hidden" name="page" value="{$_GET['page']}"/>
<input type="hidden" name="pi" value="{$_GET['pi']}"/>
<button name="submit" value="process">Process</button>
</form>
END;
