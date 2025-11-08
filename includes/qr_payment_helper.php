<?php
/**
 * QR Payment Helper
 * Generuje QR kódy pro platby podle českého (SPD/SPAYD) a evropského (EPC/SEPA) standardu
 */

class QRPaymentHelper {

    /**
     * Generuje SPD (Short Payment Descriptor) string pro český QR kód platby
     *
     * @param array $data Pole s parametry platby
     *   - acc: IBAN účtu příjemce (povinné)
     *   - am: částka (nepovinné)
     *   - cc: měna, výchozí CZK (nepovinné)
     *   - vs: variabilní symbol (nepovinné)
     *   - ss: specifický symbol (nepovinné)
     *   - ks: konstantní symbol (nepovinné)
     *   - msg: zpráva pro příjemce (nepovinné)
     *   - id: identifikátor platby (nepovinné)
     *   - dt: datum splatnosti YYYYMMDD (nepovinné)
     * @return string SPD formátovaný string
     */
    public static function generateSPD($data) {
        // Validace povinných polí
        if (empty($data['acc'])) {
            throw new InvalidArgumentException('Account (acc) is required for SPD');
        }

        // Začínáme s verzí SPD
        $spd = 'SPD*1.0';

        // Účet (IBAN)
        $spd .= '*ACC:' . self::sanitizeIBAN($data['acc']);

        // Částka
        if (!empty($data['am'])) {
            $amount = number_format((float)$data['am'], 2, '.', '');
            $spd .= '*AM:' . $amount;
        }

        // Měna (výchozí CZK)
        $currency = $data['cc'] ?? 'CZK';
        $spd .= '*CC:' . strtoupper($currency);

        // Variabilní symbol
        if (!empty($data['vs'])) {
            $spd .= '*X-VS:' . preg_replace('/[^0-9]/', '', $data['vs']);
        }

        // Specifický symbol
        if (!empty($data['ss'])) {
            $spd .= '*X-SS:' . preg_replace('/[^0-9]/', '', $data['ss']);
        }

        // Konstantní symbol
        if (!empty($data['ks'])) {
            $spd .= '*X-KS:' . preg_replace('/[^0-9]/', '', $data['ks']);
        }

        // Identifikátor platby
        if (!empty($data['id'])) {
            $spd .= '*X-ID:' . self::sanitizeString($data['id']);
        }

        // Zpráva pro příjemce
        if (!empty($data['msg'])) {
            $spd .= '*MSG:' . self::sanitizeString($data['msg']);
        }

        // Datum splatnosti
        if (!empty($data['dt'])) {
            $spd .= '*DT:' . preg_replace('/[^0-9]/', '', $data['dt']);
        }

        return $spd;
    }

    /**
     * Generuje EPC QR kód string pro SEPA platby v EUR
     *
     * @param array $data Pole s parametry platby
     *   - bic: BIC kód banky příjemce (nepovinné)
     *   - name: jméno příjemce (povinné)
     *   - iban: IBAN účtu příjemce (povinné)
     *   - amount: částka v EUR (nepovinné)
     *   - purpose: účel platby - kód (nepovinné)
     *   - reference: reference platby (nepovinné)
     *   - message: zpráva pro příjemce (nepovinné)
     * @return string EPC formátovaný string
     */
    public static function generateEPC($data) {
        // Validace povinných polí
        if (empty($data['iban'])) {
            throw new InvalidArgumentException('IBAN is required for EPC');
        }
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Beneficiary name is required for EPC');
        }

        $lines = [];

        // Řádek 1: Service Tag
        $lines[] = 'BCD';

        // Řádek 2: Verze
        $lines[] = '002';

        // Řádek 3: Character set (1 = UTF-8)
        $lines[] = '1';

        // Řádek 4: Identification (SCT = SEPA Credit Transfer)
        $lines[] = 'SCT';

        // Řádek 5: BIC banky příjemce (nepovinné)
        $lines[] = !empty($data['bic']) ? strtoupper($data['bic']) : '';

        // Řádek 6: Jméno příjemce (max 70 znaků)
        $lines[] = mb_substr($data['name'], 0, 70);

        // Řádek 7: IBAN příjemce
        $lines[] = self::sanitizeIBAN($data['iban']);

        // Řádek 8: Částka v EUR (formát EUR12.34)
        if (!empty($data['amount'])) {
            $amount = number_format((float)$data['amount'], 2, '.', '');
            $lines[] = 'EUR' . $amount;
        } else {
            $lines[] = '';
        }

        // Řádek 9: Purpose (účel platby - kód)
        $lines[] = !empty($data['purpose']) ? substr($data['purpose'], 0, 4) : '';

        // Řádek 10: Structured reference (max 35 znaků)
        $lines[] = !empty($data['reference']) ? substr($data['reference'], 0, 35) : '';

        // Řádek 11: Unstructured remittance (zpráva, max 140 znaků)
        $lines[] = !empty($data['message']) ? mb_substr($data['message'], 0, 140) : '';

        // Řádek 12: Beneficiary to originator information (nepovinné)
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Sanitizuje IBAN - odstraní mezery a převede na velká písmena
     */
    private static function sanitizeIBAN($iban) {
        $iban = str_replace(' ', '', $iban);
        $iban = strtoupper($iban);

        // Základní validace formátu IBAN (2 písmena + čísla)
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            throw new InvalidArgumentException('Invalid IBAN format');
        }

        return $iban;
    }

    /**
     * Sanitizuje string pro QR kód
     */
    private static function sanitizeString($str) {
        // Odstraní speciální znaky které by mohly způsobit problémy
        $str = str_replace(['*', "\n", "\r"], '', $str);
        return $str;
    }

    /**
     * Validuje a formátuje částku
     */
    public static function validateAmount($amount) {
        $amount = str_replace(',', '.', $amount);
        $amount = (float)$amount;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero');
        }

        return $amount;
    }

    /**
     * Generuje testovací SPD QR kód pro demo účely
     */
    public static function getTestSPD() {
        return self::generateSPD([
            'acc' => 'CZ5855000000001265098001',
            'am' => 1250.50,
            'cc' => 'CZK',
            'vs' => '2025001',
            'id' => 'FAKTURA123',
            'msg' => 'Platba za služby WGS'
        ]);
    }

    /**
     * Generuje testovací EPC QR kód pro demo účely
     */
    public static function getTestEPC() {
        return self::generateEPC([
            'bic' => 'GIBACZPX',
            'name' => 'White Glove Service s.r.o.',
            'iban' => 'CZ5855000000001265098001',
            'amount' => 50.00,
            'reference' => 'INV2025001',
            'message' => 'Payment for WGS services'
        ]);
    }
}
?>
