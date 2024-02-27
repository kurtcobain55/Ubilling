<?php

/**
 * Ubilling REST API implementation class
 */
class XMLAgent {
    /**
     * Defines the REST API responses format: XML or JSON
     * XML is a default
     *
     * @var string
     */
    protected $outputFormat = 'xml';

    /**
     * Placeholder for Ubilling UserStats config instance
     *
     * @var null
     */
    protected $usConfig = null;

    /**
     * Placeholder for PAYMENTS_ENABLED "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgPaymentsON = 0;

    /**
     * Placeholder for AN_ENABLED "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgAnnouncementsON = 0;

    /**
     * Placeholder for TICKETING_ENABLED "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgTicketingON = 0;

    /**
     * Placeholder for UBA_XML_ADDRESS_STRUCT "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgAddressStructON = 0;

    /**
     * Placeholder for ONLINELEFT_COUNT "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgOnlineLeftCountON = 0;

    /**
     * Placeholder for OPENPAYZ_ENABLED "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgOpenPayzON = 0;

    /**
     * Placeholder for OPENPAYZ_REALID "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgOpenPayzRealIDON = 0;

    /**
     * Placeholder for OPENPAYZ_URL "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgOpenPayzURL = '../openpayz/backend/';

    /**
     * Placeholder for OPENPAYZ_PAYSYS "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgOpenPayzPaySys = '';

    /**
     * Placeholder for currency "userstats.ini" option
     *
     * @var int
     */
    protected $uscfgCurrency = 'UAH';

    /**
     * Placeholder for the whole "opayz.ini" config contents
     *
     * @var int
     */
    protected $usOpayzCfg = array();


    public function __construct($user_login = '') {
        $this->loadConfig();
        $this->loadOptions();
        $this->outputFormat = (ubRouting::checkGet('json') ? 'json' : 'xml');
        $this->router($user_login);
    }


    /**
     * Get the UserStatsConfig instance
     *
     * @return void
     */
    protected function loadConfig() {
        $this->usConfig = new UserStatsConfig();
    }


    /**
     * Essential options loader
     *
     * @return void
     */
    protected function loadOptions() {
        $this->usOpayzCfg               = $this->usConfig->getOpayzCfg();
        $this->uscfgPaymentsON          = $this->usConfig->getUstasParam('PAYMENTS_ENABLED', 0);
        $this->uscfgAnnouncementsON     = $this->usConfig->getUstasParam('AN_ENABLED', 0);
        $this->uscfgTicketingON         = $this->usConfig->getUstasParam('TICKETING_ENABLED', 0);
        $this->uscfgAddressStructON     = $this->usConfig->getUstasParam('UBA_XML_ADDRESS_STRUCT', 0);
        $this->uscfgOnlineLeftCountON   = $this->usConfig->getUstasParam('ONLINELEFT_COUNT', 0);
        $this->uscfgOpenPayzON          = $this->usConfig->getUstasParam('OPENPAYZ_ENABLED', 0);
        $this->uscfgOpenPayzRealIDON    = $this->usConfig->getUstasParam('OPENPAYZ_REALID', 0);
        $this->uscfgOpenPayzURL         = $this->usConfig->getUstasParam('OPENPAYZ_URL', '../openpayz/backend/');
        $this->uscfgOpenPayzPaySys      = $this->usConfig->getUstasParam('OPENPAYZ_PAYSYS', 0);
        $this->uscfgCurrency            = $this->usConfig->getUstasParam('currency', 'UAH');
    }


    /**
     * Chooses the destination according to GET params
     *
     * @param $user_login
     *
     * @return void
     */
    public function router($user_login) {
        $outputFormat   = $this->outputFormat;
        $resultToRender = array();
        $mainSection    = 'data';
        $subSection     = '';
        $messages       = false;
        $getUserData    = !(ubRouting::checkGet('payments')
                            or ubRouting::checkGet('announcements')
                            or ubRouting::checkGet('tickets')
                            or ubRouting::checkGet('opayz')
                            or ubRouting::checkGet('agentassigned')
                           );

        if (!empty($user_login)) {
            if ($getUserData) {
                $mainSection    = 'userdata';
                $resultToRender = $this->getUserData($user_login);
            }
            else {
                if (ubRouting::checkGet('payments') and $this->uscfgPaymentsON) {
                    $subSection     = 'payment';
                    $resultToRender = $this->getUserPayments($user_login);
                }

                if (ubRouting::checkGet('tickets') and $this->uscfgTicketingON) {
                    $subSection     = 'ticket';
                    $resultToRender = $this->getUserTickets($user_login);
                }

                if (ubRouting::checkGet('opayz') and $this->uscfgOpenPayzON) {
                    $subSection     = 'paysys';
                    $resultToRender = $this->getUserOpenPayz($user_login);
                }

                if (ubRouting::checkGet('agentassigned')) {
                    $subSection     = 'agentdata';
                    $resultToRender = $this->getUserAgent($user_login);
                }
            }
        }

        if (ubRouting::checkGet('announcements') and $this->uscfgAnnouncementsON) {
            $messages       = true;
            $resultToRender = $this->getAnnouncements();
        }

        $this->renderResponse($resultToRender, $mainSection, $subSection, $outputFormat, $messages);
    }


    /**
     * Renders error response when the XMLAgent is disabled by config
     *
     * @return void
     */
    public function renderDisabledErrorResponse() {
        $this->renderResponse(array(array('reason' => 'disabled')), 'error', '', $this->outputFormat);
    }


    /**
     * Renders some data as XML/JSON
     *
     * @param array $data data array for rendering
     * @param string $mainSection all output data parent element tag name
     * @param string $subSection parent tag for each data qunique element tag name
     * @param string $format output format: xml or json
     * @param bool $messages is data contain announcements data for render
     *
     * @return void
     */
    public static function renderResponse($data, $mainSection = '', $subSection = '', $format = 'xml', $messages = false) {
        $result = '';
        //XML legacy output
        if ($format == 'xml') {
            $result .= '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;

            if (!empty($mainSection)) {
                $result .= '<' . $mainSection . '>' . PHP_EOL;
            }

            if (!empty($data)) {
                foreach ($data as $index => $record) {
                    if (!empty($subSection)) {
                        $result .= '<' . $subSection . '>' . PHP_EOL;
                    }

                    //normal data output
                    if (!$messages) {
                        foreach ($record as $tag => $value) {
                            $result .= "\t" . '<' . $tag . '>' . $value . '</' . $tag . '>' . PHP_EOL;
                        }
                    } else {
                        //announcements data output
                        $result .= '<message unic="' . $record['unic'] . '" title="' . $record['title'] . '">' . $record['text'] . '</message>' . PHP_EOL;
                    }

                    if (!empty($subSection)) {
                        $result .= '</' . $subSection . '>' . PHP_EOL;
                    }
                }
            }

            if (!empty($mainSection)) {
                $result .= '</' . $mainSection . '>' . PHP_EOL;
            }
        }

        //JSON data output
        if ($format == 'json') {
            $jsonData = array();
            $rcount = 0;

            if (!empty($data)) {
                foreach ($data as $index => $record) {
                    if (!empty($record)) {
                        foreach ($record as $tag => $value) {
                            if (!empty($subSection) OR $messages) {
                                $jsonData[$rcount][$tag] = $value;
                            } else {
                                $jsonData[$tag] = $value;
                            }
                        }
                    }

                    $rcount++;
                }
            }

            $result .= json_encode($jsonData);
        }


        //pushing result to client
        $contentType = 'text';

        if ($format == 'json') {
            $contentType = 'application/json';
        }

        header('Last-Modified: ' . gmdate('r'));
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Pragma: no-cache");
        header('Access-Control-Allow-Origin: *');
        die($result);
    }


    /**
     * Data collector for "payments" request
     *
     * @param $login
     *
     * @return array
     */
    protected function getUserPayments($login) {
        $payments = array();
        $allpayments = zbs_CashGetUserPayments($login);

        if (!empty($allpayments)) {
            foreach ($allpayments as $io => $eachpayment) {
                $payments[$eachpayment['id']]['date']    = $eachpayment['date'];
                $payments[$eachpayment['id']]['summ']    = $eachpayment['summ'];
                $payments[$eachpayment['id']]['balance'] = $eachpayment['balance'];
            }
        }

        return ($payments);
    }


    /**
     * Data collector for "announcements" request
     *
     * @return array
     */
    protected function getAnnouncements() {
        $annArr     = array();
        $annTable   = new NyanORM('zbsannouncements');
        $annTable->where('public', '=', '1');
        $annTable->orderBy('id', 'DESC');
        $allAnnouncements = $annTable->getAll();

        if (!empty($allAnnouncements)) {
            foreach ($allAnnouncements as $ian => $eachAnnouncement) {
                $annText = strip_tags($eachAnnouncement['text']);
                $allTitle = strip_tags($eachAnnouncement['title']);
                $annArr[] = array(
                    'unic' => $eachAnnouncement['id'],
                    'title' => $allTitle,
                    'text' => $annText
                );
            }
        }

        return ($annArr);
    }


    /**
     * Data collector for "tickets" request
     *
     * @param $login
     *
     * @return array
     */
    protected function getUserTickets($login) {
        $ticketsArr     = array();
        $myTickets      = array();
        $ticketsTable   = new NyanORM('ticketing');
        $ticketsTable->orderBy('date', 'DESC');
        $allTickets     = $ticketsTable->getAll();

        if (!empty($allTickets)) {
            foreach ($allTickets as $io => $each) {
                if ($each['from'] == $login or $each['to'] == $login or isset($myTickets[$each['replyid']])) {
                    $myTickets[$each['id']]             = $each['id'];
                    $ticketsArr[$each['id']]['id']      = $each['id'];
                    $ticketsArr[$each['id']]['date']    = $each['date'];
                    $ticketsArr[$each['id']]['from']    = $each['from'];
                    $ticketsArr[$each['id']]['to']      = $each['to'];
                    $ticketsArr[$each['id']]['replyid'] = $each['replyid'];
                    $ticketsArr[$each['id']]['status']  = $each['status'];
                    $ticketsArr[$each['id']]['text']    = $each['text'];
                }
            }
        }

        return ($ticketsArr);
    }


    /**
     * Data collector for "opayz" request
     *
     * @param $login
     *
     * @return array
     */
    protected function getUserOpenPayz($login) {
        $opayzArr       = array();
        $paySys         = explode(",", $this->uscfgOpenPayzPaySys);
        $payDesc        = (empty($this->usOpayzCfg) ? array() : $this->usOpayzCfg);
        $opayzPaymentid = 0;

        if ($this->uscfgOpenPayzRealIDON) {
            $opayzPaymentid = zbs_PaymentIDGet($login);
        } else {
            $userdata = zbs_UserGetStargazerData($login);
            $opayzPaymentid = ip2int($userdata['IP']);
        }

        if (!empty($paySys)) {
            if (!empty($opayzPaymentid)) {
                foreach ($paySys as $io => $eachpaysys) {
                    if (isset($payDesc[$eachpaysys])) {
                        $paysys_desc = $payDesc[$eachpaysys];
                    } else {
                        $paysys_desc = '';
                    }

                    $paymentUrl = $this->uscfgOpenPayzURL . $eachpaysys . '/?customer_id=' . $opayzPaymentid;
                    $opayzArr[$eachpaysys]['name'] = $eachpaysys;
                    $opayzArr[$eachpaysys]['url'] = $paymentUrl;
                    $opayzArr[$eachpaysys]['description'] = $paysys_desc;
                }
            }
        }

        return ($opayzArr);
    }


    /**
     * Data collector for "agentassigned" request
     *
     * @param $login
     *
     * @return array
     */
    protected function getUserAgent($login) {
        $allAddress = zbs_AddressGetFulladdresslist();
        $userAddress = empty($allAddress) ? array() : $allAddress[$login];
        $agentData = zbs_AgentAssignedGetDataFast($login, $userAddress);
        $agentArray = empty($agentData) ? array() : array('agentdata' => $agentData);

        return ($agentArray);
    }


    /**
     * Data collector for "userdata" request
     *
     * @param $login
     *
     * @return array
     */
    protected function getUserData($login) {
        $userdata = zbs_UserGetStargazerData($login);
        $alladdress = zbs_AddressGetFulladdresslist();

        if ($this->uscfgAddressStructON) {
            $alladdressStruct = zbs_AddressGetFulladdresslistStruct($login);
        } else {
            $alladdressStruct = array();
        }

        $allrealnames = zbs_UserGetAllRealnames();
        $contract = zbs_UserGetContract($login);
        $email = zbs_UserGetEmail($login);
        $mobile = zbs_UserGetMobile($login);
        $phone = zbs_UserGetPhone($login);
        $apiVer = '1';

        $passive = $userdata['Passive'];
        $down = $userdata['Down'];

        //payment id handling
        if ($this->uscfgOpenPayzON) {
            if ($this->uscfgOpenPayzRealIDON) {
                $paymentid = zbs_PaymentIDGet($login);
            } else {
                $paymentid = ip2int($userdata['IP']);
            }
        } else {
            $paymentid = 0;
        }

        if ($userdata['CreditExpire'] != 0) {
            $credexpire = date("d-m-Y", $userdata['CreditExpire']);
        } else {
            $credexpire = 'No';
        }

        if ($userdata['TariffChange']) {
            $tariffNm = $userdata['TariffChange'];
        } else {
            $tariffNm = 'No';
        }
        $traffdown = 0;
        $traffup = 0;
        $traffdgb = 0;
        $traffugb = 0;

        for ($i = 0; $i <= 9; $i++) {
            $traffdown = $traffdown + $userdata['D' . $i];
            $traffup = $traffup + $userdata['U' . $i];
        }

        $traffdgb = round($traffdown / 1073741824);
        $traffugb = round($traffup / 1073741824);

        if ($traffdgb == 0) {
            $traffdgb = 1;
        }

        if ($traffugb == 0) {
            $traffugb = 1;
        }

        // pasive state check
        if ($passive) {
            $passive_state = 'frozen';
        } else {
            $passive_state = 'active';
        }

        //down state check
        if ($down) {
            $down_state = ' + disabled';
        } else {
            $down_state = '';
        }

        // START OF ONLINELEFT COUNTING <<
        if ($this->uscfgOnlineLeftCountON) {
            // DEFINE VARS:
            $userBalance = $userdata['Cash'];
            if ($userBalance >= 0) {
                $balanceExpire = zbs_GetOnlineLeftCount($login, $userBalance, $userdata['Tariff'], true);
            } else {
                $balanceExpire = 'debt';
            }
        } else {
            $balanceExpire = 'No';
        }
        // >> END OF ONLINELEFT COUNTING

        $reqResult = array();
        $reqResult[] = array('address' => @$alladdress[$login]);
        if ($this->uscfgAddressStructON) {
            if (!empty($alladdressStruct)) {
                foreach ($alladdressStruct[$login] as $field => $value) {
                    $reqResult[] = array($field => $value);
                }
            }
        }
        $reqResult[] = array('realname' => @$allrealnames[$login]);
        $reqResult[] = array('login' => $login);
        $reqResult[] = array('cash' => @round($userdata['Cash'], 2));
        $reqResult[] = array('ip' => @$userdata['IP']);
        $reqResult[] = array('phone' => $phone);
        $reqResult[] = array('mobile' => $mobile);
        $reqResult[] = array('email' => $email);
        $reqResult[] = array('credit' => @$userdata['Credit']);
        $reqResult[] = array('creditexpire' => $credexpire);
        $reqResult[] = array('payid' => strval($paymentid));
        $reqResult[] = array('contract' => $contract);
        $reqResult[] = array('tariff' => $userdata['Tariff']);
        $reqResult[] = array('tariffalias' => __($userdata['Tariff']));
        $reqResult[] = array('tariffnm' => $tariffNm);
        $reqResult[] = array('traffdownload' => zbs_convert_size($traffdown));
        $reqResult[] = array('traffupload' => zbs_convert_size($traffup));
        $reqResult[] = array('trafftotal' => zbs_convert_size($traffdown + $traffup));
        $reqResult[] = array('accountstate' => $passive_state . $down_state);
        $reqResult[] = array('accountexpire' => $balanceExpire);
        $reqResult[] = array('currency' => $this->uscfgCurrency);
        $reqResult[] = array('version' => $apiVer);

        return ($reqResult);
    }

}