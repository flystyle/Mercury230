<?php

class Mercury230
{
    public $sock;
    public $host;
    public $port;
    public $addr;
    public $timeout = 5;

    function __construct( $host, $port, $addr )
    {
        $this->host = $host;
        $this->port = $port;
        $this->addr = dechex($addr);
        $this->sock = false;
        $this->error = false;
        $this->message = '';
        $this->lock = fopen(__DIR__ . '/run.lock', 'c');
    }

    // Check connection
    public function check_sock($bool = false)
    {
        $error = true;
        $result = $this->send('00', true);

        $crc = substr($result, -4);
        $code = substr($result, 2, 2);

        // Check CRC
        $crc16 = $this->crc16_modbus( hex2bin(substr($result, 0, -4)) );
        $crc16 = bin2hex($crc16);
        $crc_check = ( $crc16 === $crc ) ? 'OK' : 'FAIL';

        // Check status
        $status = ( $code === '00' ) ? 'OK' : 'FAIL';

        if ( $bool ) {
            if ( $crc_check === 'OK' && $status === 'OK' ) $error = false;
        } else {
            $error = "CRC: $crc_check | CONNECTION: $status | ANSWER: $result";
        }

        return $error;
    }


    public function get_meter() {
        $data = array();
        $meter = $this->send('0801');

        $data['sn'] = hexdec( substr($meter, 0, 2) )
                . hexdec( substr($meter, 2, 2) )
                . hexdec( substr($meter, 4, 2) )
                . hexdec( substr($meter, 6, 2) );
        $data['date'] = sprintf('%02d', hexdec( substr($meter, 8, 2) ))
                . "." . sprintf('%02d', hexdec( substr($meter, 10, 2) ))
                . "." . sprintf('%02d', hexdec( substr($meter, 12, 2) ));
        $data['ver'] = hexdec( substr($meter, 14, 2) )
                . "." . hexdec( substr($meter, 16, 2) )
                . "." . hexdec( substr($meter, 18, 2) );

        return $data;
    }


    public function get_moment() {
        $data = array();
        $result = '';

        $resp = $this->send('0816A0');

        // 3. Поле данных ответа в режиме чтения вспомогательных параметров:
        // мощности P, Q, S по сумме фаз и фазам (36 байт);
        // фазные напряжения (9 байт);
        // углы между фазными напряжениями (9 байт);
        // токи (9 байт);
        // коэффициенты мощности по сумме фаз и фазам (12 байт);
        // частота сети (3 байта);
        // коэффициенты гармоник фазных напряжений (6 байт);
        // температура внутри прибора (2 байта);
        // межфазные значения (9 байт)*.
        // * Возможная информация.

        $info = array(
            'Psum',
            'P1',
            'P2',
            'P3',
            'Qsum',
            'Q1',
            'Q2',
            'Q3',
            'Ssum',
            'S1',
            'S2',
            'S3',
            'U1',
            'U2',
            'U3',
            'Fab',
            'Fac',
            'Fbc',
            'I1',
            'I2',
            'I3',
            'cFsum',
            'cF1',
            'cF2',
            'cF3',
            'Hz'
        );

        if ( $this->error ) {
            $result['error'] = true;
            $result['err_msg'] = $this->message;
        }

        if ( !empty($resp) ) {
            $data = $this->hexarr_dec( $resp, 6, 1 );
            $result = array_combine($info, $data);
        }

        return $result;

    }


    public function get_stored() {
        $data = array();

        //
        // Накопленные значения
        // 05 - код запроса
        // 00 - № массива:
        //// 00 - от сброса,
        //// 10 - за этот год
        //// 20 - пред. год
        //// 3х - за месяц, где х - номер месяца
        //// 40 - сутки
        //// 50 - пред. сутки
        //// 60 - пофазные значения
        // 00 - по сумме тарифов, 01-04 номер тарифа
        //

        $send = array(
            't1' => '050001',
            't2' => '050002',
            'total' => '050000',
            'day_t1' => '054001',
            'day_t2' => '054002',
            'day_total' => '054000',
        );

        foreach ( $send as $key => $cmd ) {

            $resp = $this->send($cmd);

            if ( !empty($resp) ) {
                $value = $this->hexarr_dec( $resp, 8, 2 );

                if ( is_numeric($value[0]) ) {
                    $data[$key] = $value[0];
                }
            }

            sleep(1);
        }

        if ( $this->error ) {
            $data['error'] = true;
            $data['err_msg'] = $this->message;
        }

        return $data;
    }


    public function send( $code, $full = false )
    {
        $this->error = false;
        $this->message = '';

        if ( $this->sock === false ) {
            die('Error: Connection failed');
        }

        if ( empty($code) ) {
            $this->error = true;
            $this->message = 'Empty code';
            exit();
        }

        $cmd = $this->addr . $code;
        $cmd .= bin2hex( $this->crc16_modbus(hex2bin($cmd)) );

        fwrite( $this->sock, hex2bin($cmd) );

        $result = '';
        $c = '';

        stream_set_blocking( $this->sock, 0 );
        $timeout = microtime(1) + 0.5;

        while ( microtime(1) < $timeout ) {
            $c = fgetc($this->sock);

            if ( $c === false ) {
                usleep(5);
                continue;
            }

            $result .= $c;
        }

        $result = bin2hex( $result );
        $crc = substr($result, -4);

        // Check CRC
        $crc16 = $this->crc16_modbus( hex2bin(substr($result, 0, -4)) );
        $crc16 = bin2hex($crc16);

        if ( $crc16 !== $crc ) {
            $this->error = true;
            $this->message = 'CRC Error';
            exit();
        }

        if ( strlen($result) < 4 ) {
            $this->error = true;
            $err = hexdec( substr($result, 0, 2) );

            switch ($err) {
                case '01':
                    $this->message = 'Ошибка: Недопустимая команда или параметр';
                    break;
                case '02':
                    $this->message = 'Ошибка: Внутренняя ошибка счетчика';
                    break;
                case '03':
                    $this->message = 'Ошибка: Недостаточен уровень доступа для выполнения запроса';
                    break;
                case '04':
                    $this->message = 'Ошибка: Внутренние часы счетчика уже корректировались в течение текущих суток';
                    break;
                case '05':
                    $this->message = 'Ошибка: Не открыт канал связи';
                    break;
            }
        }

        if ( $full === false ) {
            $result = substr($result, 2, -4);
        }

        return $result;
    }


    public function open()
    {
        $this->sock = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if ($this->sock === false) {
            die("Couldn't create socket: [$errno] $errstr");
        }

        $lock = true;
        $count = 0;

        while (!flock($this->lock, LOCK_EX | LOCK_NB, $wb)) {
            if ($wb && $count++ < $this->timeout) {
                sleep(1);
            } else {
                $lock = false;
                break;
            }
        }

        if ($lock) {
            // Аутентификация по первому уровню доступа
            $resp = $this->send('0101010101010101');
            $resp = intval($resp);
            $result = ( $resp === 0 ) ? 'OK' : 'FAIL: ' . $resp;

            return $result;
        } else {
            return 'FAIL: run.lock timeout';
        }
    }


    public function close()
    {
        $resp = $this->send('02');
        $result = ( $resp === '00' ) ? 'OK' : 'FAIL';
        fclose($this->sock);
        flock($this->lock, LOCK_UN);
        fclose($this->lock);

        return $result;
    }


    private function hexarr_dec( $str, $bytes = 2, $v = 1 ) {
        if ( empty($str) )
            exit();

        $data = array();
        $arr = array_map(null, str_split($str, $bytes));

        if ($v === 1) {
            foreach ($arr as $a) {
                $str = "0" . $a[1] . substr($a, 4,  2) . substr($a, 2,  2);
                $data[] = hexdec($str);
            }
        }
        elseif ($v === 2) {
            foreach ($arr as $a) {
                if (strpos($a, 'ffff') === false) {
                    $str = substr($a, 2,  2) . substr($a, 0,  2) . substr($a, 6,  2) . substr($a, 4,  2);
                    $data[] = hexdec($str);
                }
            }
        }

        return $data;
    }


    private function hex_arr($h) {
        return array_map(null, str_split($h, 2));
    }


    // Format input string as nice hex
    public function nice_hex( $str ) {
        $res = substr($str, 2, -4);
        return strtoupper( implode(' ', str_split($res, 2)) );
    }


    // Calculate modbus crc 16
    function crc16_modbus( $string ) {
        $auchCRCHi=array( 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
                    0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
                    0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
                    0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41,
                    0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81,
                    0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0,
                    0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
                    0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40,
                    0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
                    0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
                    0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01,
                    0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
                    0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
                    0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0,
                    0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01,
                    0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81, 0x40, 0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41,
                    0x00, 0xC1, 0x81, 0x40, 0x01, 0xC0, 0x80, 0x41, 0x01, 0xC0, 0x80, 0x41, 0x00, 0xC1, 0x81,
                    0x40);
        $auchCRCLo=array(    0x00, 0xC0, 0xC1, 0x01, 0xC3, 0x03, 0x02, 0xC2, 0xC6, 0x06, 0x07, 0xC7, 0x05, 0xC5, 0xC4,
                    0x04, 0xCC, 0x0C, 0x0D, 0xCD, 0x0F, 0xCF, 0xCE, 0x0E, 0x0A, 0xCA, 0xCB, 0x0B, 0xC9, 0x09,
                    0x08, 0xC8, 0xD8, 0x18, 0x19, 0xD9, 0x1B, 0xDB, 0xDA, 0x1A, 0x1E, 0xDE, 0xDF, 0x1F, 0xDD,
                    0x1D, 0x1C, 0xDC, 0x14, 0xD4, 0xD5, 0x15, 0xD7, 0x17, 0x16, 0xD6, 0xD2, 0x12, 0x13, 0xD3,
                    0x11, 0xD1, 0xD0, 0x10, 0xF0, 0x30, 0x31, 0xF1, 0x33, 0xF3, 0xF2, 0x32, 0x36, 0xF6, 0xF7,
                    0x37, 0xF5, 0x35, 0x34, 0xF4, 0x3C, 0xFC, 0xFD, 0x3D, 0xFF, 0x3F, 0x3E, 0xFE, 0xFA, 0x3A,
                    0x3B, 0xFB, 0x39, 0xF9, 0xF8, 0x38, 0x28, 0xE8, 0xE9, 0x29, 0xEB, 0x2B, 0x2A, 0xEA, 0xEE,
                    0x2E, 0x2F, 0xEF, 0x2D, 0xED, 0xEC, 0x2C, 0xE4, 0x24, 0x25, 0xE5, 0x27, 0xE7, 0xE6, 0x26,
                    0x22, 0xE2, 0xE3, 0x23, 0xE1, 0x21, 0x20, 0xE0, 0xA0, 0x60, 0x61, 0xA1, 0x63, 0xA3, 0xA2,
                    0x62, 0x66, 0xA6, 0xA7, 0x67, 0xA5, 0x65, 0x64, 0xA4, 0x6C, 0xAC, 0xAD, 0x6D, 0xAF, 0x6F,
                    0x6E, 0xAE, 0xAA, 0x6A, 0x6B, 0xAB, 0x69, 0xA9, 0xA8, 0x68, 0x78, 0xB8, 0xB9, 0x79, 0xBB,
                    0x7B, 0x7A, 0xBA, 0xBE, 0x7E, 0x7F, 0xBF, 0x7D, 0xBD, 0xBC, 0x7C, 0xB4, 0x74, 0x75, 0xB5,
                    0x77, 0xB7, 0xB6, 0x76, 0x72, 0xB2, 0xB3, 0x73, 0xB1, 0x71, 0x70, 0xB0, 0x50, 0x90, 0x91,
                    0x51, 0x93, 0x53, 0x52, 0x92, 0x96, 0x56, 0x57, 0x97, 0x55, 0x95, 0x94, 0x54, 0x9C, 0x5C,
                    0x5D, 0x9D, 0x5F, 0x9F, 0x9E, 0x5E, 0x5A, 0x9A, 0x9B, 0x5B, 0x99, 0x59, 0x58, 0x98, 0x88,
                    0x48, 0x49, 0x89, 0x4B, 0x8B, 0x8A, 0x4A, 0x4E, 0x8E, 0x8F, 0x4F, 0x8D, 0x4D, 0x4C, 0x8C,
                    0x44, 0x84, 0x85, 0x45, 0x87, 0x47, 0x46, 0x86, 0x82, 0x42, 0x43, 0x83, 0x41, 0x81, 0x80,
                    0x40);
        $length = strlen($string);
        $uchCRCHi   = 0xFF;
        $uchCRCLo   = 0xFF;
        $uIndex     = 0;
        for ($i=0;$i<$length;$i++) {
            $uIndex     = $uchCRCLo ^ ord(substr($string,$i,1));
            $uchCRCLo   = $uchCRCHi ^ $auchCRCHi[$uIndex];
            $uchCRCHi   = $auchCRCLo[$uIndex] ;
        }
        return(chr($uchCRCLo).chr($uchCRCHi));
    }
}
