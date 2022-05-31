<?php

/**
 * Basic PON OLTs devices collectors hardware abstraction layer prototype
 */
class PONProto {

    /**
     * Contains available SNMP templates for OLT modelids
     *
     * @var array
     */
    protected $snmpTemplates = array();

    /**
     * SNMPHelper object instance
     *
     * @var array
     */
    protected $snmp = '';

    /**
     * Replicated paths from primary PONizer class
     */
    const SIGCACHE_PATH = PONizer::SIGCACHE_PATH;
    const SIGCACHE_EXT = PONizer::SIGCACHE_EXT;
    const DISTCACHE_PATH = PONizer::DISTCACHE_PATH;
    const DISTCACHE_EXT = PONizer::DISTCACHE_EXT;
    const ONUCACHE_PATH = PONizer::ONUCACHE_PATH;
    const ONUCACHE_EXT = PONizer::ONUCACHE_EXT;
    const INTCACHE_PATH = PONizer::INTCACHE_PATH;
    const INTCACHE_EXT = PONizer::INTCACHE_EXT;
    const INTDESCRCACHE_EXT = PONizer::INTDESCRCACHE_EXT;
    const FDBCACHE_PATH = PONizer::FDBCACHE_PATH;
    const FDBCACHE_EXT = PONizer::FDBCACHE_EXT;
    const DEREGCACHE_PATH = PONizer::DEREGCACHE_PATH;
    const DEREGCACHE_EXT = PONizer::DEREGCACHE_EXT;
    const UPTIME_PATH = PONizer::UPTIME_PATH;
    const UPTIME_EXT = PONizer::UPTIME_EXT;
    const TEMPERATURE_PATH = PONizer::TEMPERATURE_PATH;
    const TEMPERATURE_EXT = PONizer::TEMPERATURE_EXT;
    const MACDEVIDCACHE_PATH = PONizer::MACDEVIDCACHE_PATH;
    const MACDEVIDCACHE_EXT = PONizer::MACDEVIDCACHE_EXT;
    const ONUSIG_PATH = PONizer::ONUSIG_PATH;
    const SNMPCACHE = PONizer::SNMPCACHE;
    const SNMPPORT = PONizer::SNMPPORT;

    /**
     * Creates new PON poller/parser proto
     * 
     * @param array $snmpTemplates
     */
    public function __construct($snmpTemplates) {
        $this->snmpTemplates = $snmpTemplates;
        $this->initSNMP();
    }

    /**
     * Creates single instance of SNMPHelper object
     *
     * @return void
     */
    protected function initSNMP() {
        $this->snmp = new SNMPHelper();
    }

    /**
     * Performs signal preprocessing for sig/mac index arrays and stores it into cache
     *
     * @param int $oltid
     * @param array $sigIndex
     * @param array $macIndex
     * @param array $snmpTemplate
     *
     * @return void
     */
    protected function signalParse($oltid, $sigIndex, $macIndex, $snmpTemplate) {
        $oltid = vf($oltid, 3);
        $sigTmp = array();
        $macTmp = array();
        $result = array();
        $curDate = curdatetime();

//signal index preprocessing
        if ((!empty($sigIndex)) and ( !empty($macIndex))) {
            foreach ($sigIndex as $io => $eachsig) {
                $line = explode('=', $eachsig);
//signal is present
                if (isset($line[1])) {
                    $signalRaw = trim($line[1]); // signal level
                    $devIndex = trim($line[0]); // device index
                    if ($signalRaw == $snmpTemplate['DOWNVALUE']) {
                        $signalRaw = 'Offline';
                    } else {
                        if ($snmpTemplate['OFFSETMODE'] == 'div') {
                            if ($snmpTemplate['OFFSET']) {
                                if (is_numeric($signalRaw)) {
                                    $signalRaw = $signalRaw / $snmpTemplate['OFFSET'];
                                } else {
                                    $signalRaw = 'Fail';
                                }
                            }
                        }
                    }
                    $sigTmp[$devIndex] = $signalRaw;
                }
            }

//mac index preprocessing
            foreach ($macIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $macTmp[$devIndex] = $macRaw;
                }
            }

//storing results
            if (!empty($macTmp)) {
                foreach ($macTmp as $devId => $eachMac) {
                    if (isset($sigTmp[$devId])) {
                        $signal = $sigTmp[$devId];
                        $result[$eachMac] = $signal;
//signal history filling
                        $historyFile = self::ONUSIG_PATH . md5($eachMac);
                        if ($signal == 'Offline') {
                            $signal = $this->onuOfflineSignalLevel; //over 9000 offline signal level :P
                        }
                        file_put_contents($historyFile, $curDate . ',' . $signal . "\n", FILE_APPEND);
                    }
                }

                $result = serialize($result);
                file_put_contents(self::SIGCACHE_PATH . $oltid . '_' . self::SIGCACHE_EXT, $result);

                // saving macindex as MAC => devID
                $macTmp = array_flip($macTmp);
                $macTmp = serialize($macTmp);
                file_put_contents(self::MACDEVIDCACHE_PATH . $oltid . '_' . self::MACDEVIDCACHE_EXT, $macTmp);
            }
        }
    }

    /**
     * Parses & stores in cache OLT ONU distances
     *
     * @param int $oltid
     * @param array $distIndex
     * @param array $onuIndex
     *
     * @return void
     */
    protected function distanceParse($oltid, $distIndex, $onuIndex) {
        $oltid = vf($oltid, 3);
        $distTmp = array();
        $onuTmp = array();
        $result = array();
        $curDate = curdatetime();

//distance index preprocessing
        if ((!empty($distIndex)) and ( !empty($onuIndex))) {
            foreach ($distIndex as $io => $eachdist) {
                $line = explode('=', $eachdist);
//distance is present
                if (isset($line[1])) {
                    $distanceRaw = trim($line[1]); // distance
                    $devIndex = trim($line[0]); // device index
                    $distTmp[$devIndex] = $distanceRaw;
                }
            }

//mac index preprocessing
            foreach ($onuIndex as $io => $eachmac) {
                $line = explode('=', $eachmac);
//mac is present
                if (isset($line[1])) {
                    $macRaw = trim($line[1]); //mac address
                    $devIndex = trim($line[0]); //device index
                    $macRaw = str_replace(' ', ':', $macRaw);
                    $macRaw = strtolower($macRaw);
                    $onuTmp[$devIndex] = $macRaw;
                }
            }

//storing results
            if (!empty($onuTmp)) {
                foreach ($onuTmp as $devId => $eachMac) {
                    if (isset($distTmp[$devId])) {
                        $distance = $distTmp[$devId];
                        $result[$eachMac] = $distance;
                    }
                }
                $result = serialize($result);
                file_put_contents(self::DISTCACHE_PATH . $oltid . '_' . self::DISTCACHE_EXT, $result);
                $onuTmp = serialize($onuTmp);
                file_put_contents(self::ONUCACHE_PATH . $oltid . '_' . self::ONUCACHE_EXT, $onuTmp);
            }
        }
    }

    /**
     * Parses BDCom uptime data and saves it into uptime cache
     *
     * @param int $oltid
     * @param string $uptimeRaw
     *
     * @return void
     */
    protected function uptimeParse($oltid, $uptimeRaw) {
        $oltid = ubRouting::filters($oltid, 'int');
        if (!empty($oltid) and ! empty($uptimeRaw)) {
            $uptimeRaw = explode(')', $uptimeRaw);
            $uptimeRaw = $uptimeRaw[1];
            $uptimeRaw = trim($uptimeRaw);
            file_put_contents(self::UPTIME_PATH . $oltid . '_' . self::UPTIME_EXT, $uptimeRaw);
        }
    }

    /**
     * Parses BDCom temperature data and saves it into uptime cache
     *
     * @param int $oltid
     * @param string $uptimeRaw
     *
     * @return void
     */
    protected function temperatureParse($oltid, $tempRaw) {
        $oltid = ubRouting::filters($oltid, 'int');
        if (!empty($oltid) and ! empty($tempRaw)) {
            $tempRaw = explode(':', $tempRaw);
            $tempRaw = $tempRaw[1];
            $tempRaw = trim($tempRaw);
            file_put_contents(self::TEMPERATURE_PATH . $oltid . '_' . self::TEMPERATURE_EXT, $tempRaw);
        }
    }

}