<?php
require_once 'vendor/autoload.php';

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\DeviceParserAbstract;

// OPTIONAL: Set version truncation to none, so full versions will be returned
// By default only minor versions will be returned (e.g. X.Y)
// for other options see VERSION_TRUNCATION_* constants in DeviceParserAbstract class
// DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);

// for mac opened files
ini_set('auto_detect_line_endings',TRUE);


class processCSV {
    public $bots = 0,
           $rows = 0,
           $repeatUsers = 0,
           $users = [],
           $stats = [
                'link_title'    => [],
                'link_layout'   => [],
                'link_location' => [],
                'content_type'  => [],
                'link_count'    => [],
                'clicked'  => 0
           ],
           $filename,
           $filePath,
           $handle,
           $newCSV,
           $colMap,
           $device,
           $browser;

    public function __construct($directory) {
        $startTime = microtime(true);
        $this->directory = $directory;
        $this->timeStarted = microtime(true);
        $this->serverRequestTime = $_SERVER['REQUEST_TIME'];

        $files = array_diff(scandir($directory), array('.', '..'));
        // foreach file in directory
        foreach($files as $file) {
            // check if it's a CSV
            if(strpos($file, '.csv') === false ) {
                continue;
            }

            $fileStart = microtime(true);
            $filePath = $this->directory.$file;
            $this->handle = fopen($filePath, 'r');
            $this->colMap = false;

            if($this->handle !== false) {
                $newFile = $this->serverRequestTime.'-processed-'.$file;
                $this->newCSV = fopen('/Users/jj/Dropbox/mamp/sites/user-agent-csv/csv/processed/'.$newFile, 'w');

                echo "Processing $newFile\n";

                $this->buildCSV();

                // close our files
                fclose($this->newCSV);
                fclose($this->handle);
                $elapsedTime = microtime(true) - $fileStart;
                echo "$file processed into $newFile in ".(string) $elapsedTime."s\n";
            }
        }
        $this->outputData();
        $elapsedTime = microtime(true) - $this->timeStarted;
        echo "\nAll files processed in ".(string) $elapsedTime."s\n";

    }

    protected function buildCSV() {
        // what column is the user agent data in???
        while (($data = fgetcsv($this->handle, 20000, ",")) !== FALSE) {

            // PROCESS FIRST ROW AND SET HEADER ROW
            if($this->colMap === false) {
                // first row will be the column headers, so we need to locate the user agent column

                foreach($data as $index => $column) {
                    $this->colMap[$column] = $index;
                }
                // add in our new columns
                $newCols = ['os', 'device', 'device_brand', 'device_model', 'browser_type', 'browser_name', 'browser_version'];

                foreach($newCols as $colName) {
                    $this->colMap[$colName] = count($this->colMap);
                }

                // write the first line
                $this->writeHeaderRow();
                continue;
            }

            // check if we already have this user
            /*
            if(!empty($data[$this->colMap['user_id']]) && in_array($data[$this->colMap['user_id']], $this->users, true)) {
                // skip it because we only want the first entry from each user
                $this->repeatUsers++;
                continue;
            }
            $this->users[] = $data[$this->colMap['user_id']];
            */


            $dd = new DeviceDetector($data[$this->colMap['browser']]);
            // If called, getBot() will only return true if a bot was detected  (speeds up detection a bit)
            $dd->discardBotInformation();
            $dd->parse();

            if ($dd->isBot()) {
                // increse bot count
                $this->bots++;
                // discard row
                continue;
            }

            // get device and browser info
            $client = $dd->getClient(); // holds information about browser, feed reader, media player, ...
            $osInfo = $dd->getOs();
            $device = $dd->getDeviceName();
            $brand = $dd->getBrandName();
            $model = $dd->getModel();


            // Map to new order
            $csvRow = [
                'link_title' => $data[$this->colMap['link_title']],
                'link_layout' => $data[$this->colMap['link_layout']],
                'link_location' => $data[$this->colMap['link_location']],
                'content_type' => $data[$this->colMap['content_type']],
                'link_count' => $data[$this->colMap['link_count']],
                'link_clicked' => $data[$this->colMap['link_clicked']],
                'clicked' => $data[$this->colMap['clicked']],
                'os' => (isset($osInfo['name']) ? $osInfo['name'] : '') . (isset($osInfo['version']) ? ' '.$osInfo['version']: ''),
                'device' => ucfirst($device),
                'device_brand' => $brand,
                'device_model' => $model,
                'browser_type' => $client['type'],
                'browser_name' => $client['name'],
                'browser_version' => $client['version'],
                'site' => $data[$this->colMap['site']],
                'page_url' => $data[$this->colMap['page_url']],
                'referrer' => $data[$this->colMap['referrer']],
                'paragraphs' => $data[$this->colMap['paragraphs']],
                'displayed_url_1' => $data[$this->colMap['displayed_url_1']],
                'displayed_url_2' => $data[$this->colMap['displayed_url_2']],
                'displayed_url_3' => $data[$this->colMap['displayed_url_3']],
                'displayed_url_4' => $data[$this->colMap['displayed_url_4']],
                'displayed_url_5' => $data[$this->colMap['displayed_url_5']],
                'time_logged' => $data[$this->colMap['time_logged']],
                'time_updated' => $data[$this->colMap['time_updated']],
                'user_id' => $data[$this->colMap['user_id']],
                'browser' => $data[$this->colMap['browser']],
                'row' => $data[$this->colMap['row']]
            ];


            // increase total row count
            $this->rows++;
            // output to console to keep track of our process
            if($this->rows % 10000 === 0) {
                echo $this->rows. " rows processed\n";
            }
            // increase other stats
            $this->addStats($csvRow);
            // write to the CSV
            $this->writeRow($csvRow);
        }

    }


    /**
    * Writes a row to the CSV
    * @param $data ARRAY of data to write.
    */
    public function writeRow($data) {
        $headers = $this->getCSVHeaderRow();
        $rowData = [];
        // maps the data to match the row header order
        foreach($headers as $colName) {
            $rowData[] = $data[$colName];
        }

        // write the CSV
        fputcsv($this->newCSV, $rowData);
    }

    /**
    * Writes the header row to the CSV
    */
    public function writeHeaderRow() {
        $headers = $this->getCSVHeaderRow();

        // write to the CSV
        fputcsv($this->newCSV, $headers);
    }

    // Outputs our header row in the order it will eventually be mapped to
    public function getCSVHeaderRow() {
        return [
            'link_title',
            'link_layout',
            'link_location',
            'content_type',
            'link_count',
            'link_clicked',
            'clicked',
            'os',
            'device',
            'device_brand',
            'device_model',
            'browser_type',
            'browser_name',
            'browser_version',
            'site',
            'page_url',
            'referrer',
            'paragraphs',
            'displayed_url_1',
            'displayed_url_2',
            'displayed_url_3',
            'displayed_url_4',
            'displayed_url_5',
            'time_logged',
            'time_updated',
            'user_id',
            'browser',
            'row'
        ];
    }
    public function addStats($row) {
        $this->increaseStats('link_title', $row);
        $this->increaseStats('link_layout', $row);
        $this->increaseStats('link_location', $row);
        $this->increaseStats('content_type', $row);
        $this->increaseStats('link_count', $row);
        if($row['clicked'] == 1) $this->stats['clicked']++;
    }

    public function increaseStats($name, $row) {
        if(empty($name) || !isset($this->stats[$name][$row[$name]]) && empty($row[$name])) {
            return;
        }

        if(!isset($this->stats[$name][$row[$name]])) {
            $this->stats[$name][$row[$name]] = 1;
        } elseif(!empty($row[$name])) {
            $this->stats[$name][$row[$name]]++;
        }
    }

    public function outputData() {
        echo "\n\nBot Rows Deleted: $this->bots\n";
        echo "Duplicate User Rows: $this->repeatUsers\n";
        echo "Total Rows: $this->rows\n";

        $this->outputStats($this->stats);
    }

    public function outputStats($stats) {
        foreach($stats as $key => $val) {
            if(is_array($val)) {
                echo "\n$key\n";
                $this->outputStats($val);
            } else {
                echo "$key: $val\n";
            }
        }
    }
}


// run the class
new processCSV('/Users/jj/Dropbox/mamp/sites/user-agent-csv/csv/bigTest/');




