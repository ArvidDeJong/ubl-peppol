<?php

namespace Darvis\UblPeppol\Constants;

class UnitCodes
{
    /**
     * List of valid UN/ECE Recommendation 20 with Rec 21 extension unit codes
     * Source: https://www.unece.org/cefact/codesfortrade/codes_index.html
     */
    private const CODES = [
        // Common unit codes
        'C62' => 'unit',
        'HUR' => 'hour',
        'DAY' => 'day',
        'TNE' => 'tonne',
        'KGM' => 'kilogram',
        'GRM' => 'gram',
        'MTR' => 'metre',
        'CMT' => 'centimetre',
        'MMT' => 'millimetre',
        'M2'  => 'square metre',
        'MTK' => 'square metre',
        'M3'  => 'cubic metre',
        'MTQ' => 'cubic metre',
        'LTR' => 'litre',
        'MLT' => 'millilitre',
        'KWH' => 'kilowatt hour',
        'KWT' => 'kilowatt',
        'ANN' => 'year',
        'MON' => 'month',
        'WEE' => 'week',
        'DZN' => 'dozen',
        'SET' => 'set',
        'PCE' => 'piece',
        'PR'  => 'pair',
        'PK'  => 'package',
        'BG'  => 'bag',
        'BX'  => 'box',
        'CT'  => 'carton',
        'CS'  => 'case',
        'EA'  => 'each',
        'GLL' => 'gallon',
        'KTM' => 'kilometre',
        'KMT' => 'kilometre',
        'KQ'  => 'kilogram',
        'LBR' => 'pound',
        'MIN' => 'minute',
        'SEC' => 'second',
        'HIT' => 'hundred items',
        'TNE' => 'tonne',
        'TNS' => 'ton (US)',
        'TNI' => 'ton (UK)',
        'KNT' => 'knot',
        'KT'  => 'kit',
        'KUR' => 'kilovolt ampere reactive hour',
        'KVA' => 'kilovolt ampere',
        'KVR' => 'kilovar',
        'KVT' => 'kilovolt',
        'KWH' => 'kilowatt hour',
        'KWN' => 'kilowatt hour per normalized cubic metre',
        'KWO' => 'kilogram of uranium trioxide',
        'KWS' => 'kilowatt hour per standard cubic metre',
        'KWT' => 'kilowatt',
        'KX'  => 'millilitre per kilogram',
        'L10' => 'quart (US) per minute',
        'L11' => 'volt per metre',
        'L12' => 'millivolt per metre',
        'L13' => 'kilopascal per second',
        'L14' => 'kilopascal per minute',
        'L15' => 'metre per second kelvin',
        'L16' => 'metre per second bar',
        'L17' => 'cubic metre per second bar',
        'L18' => 'cubic metre per second',
        'L19' => 'cubic metre per minute bar',
        'L2'  => 'litre per minute',
        'L20' => 'cubic metre per day',
        'L21' => 'cubic metre per hour bar',
        'L23' => 'cubic metre per day bar',
        'L24' => 'cubic metre per hour kelvin',
        'L25' => 'cubic metre per day kelvin',
        'L26' => 'cubic metre per second kelvin',
        'L27' => 'cubic metre per second bar',
        'L28' => 'cubic metre per minute kelvin',
        'L29' => 'cubic centimetre per second bar',
        'L30' => 'cubic centimetre per second kelvin',
        'L31' => 'litre per second bar',
        'L32' => 'litre per second kelvin',
        'L33' => 'litre per minute bar',
        'L34' => 'litre per minute kelvin',
        'L35' => 'litre per day bar',
        'L36' => 'litre per day kelvin',
        'L37' => 'cubic metre per hour bar',
        'L38' => 'cubic metre per day bar',
        'L39' => 'cubic metre per hour kelvin',
        'L40' => 'cubic metre per day kelvin',
        'L41' => 'millilitre per second kelvin',
        'L42' => 'millilitre per second bar',
        'L43' => 'millilitre per minute kelvin',
        'L44' => 'millilitre per minute bar',
        'L45' => 'millilitre per day kelvin',
        'L46' => 'millilitre per day bar',
        'L47' => 'millilitre per hour kelvin',
        'L48' => 'millilitre per hour bar',
        'L49' => 'cubic centimetre per second bar',
        'L50' => 'cubic centimetre per second kelvin',
        'L51' => 'cubic centimetre per minute bar',
        'L52' => 'cubic centimetre per minute kelvin',
        'L53' => 'cubic centimetre per hour bar',
        'L54' => 'cubic centimetre per hour kelvin',
        'L55' => 'cubic centimetre per day bar',
        'L56' => 'cubic centimetre per day kelvin',
        'L57' => 'cubic metre per second bar',
        'L58' => 'cubic metre per second kelvin',
        'L59' => 'cubic metre per minute bar',
        'L60' => 'cubic metre per minute kelvin',
        'L61' => 'cubic metre per hour bar',
        'L62' => 'cubic metre per hour kelvin',
        'L63' => 'cubic metre per day bar',
        'L64' => 'cubic metre per day kelvin',
        'L65' => 'litre per second bar',
        'L66' => 'litre per second kelvin',
        'L67' => 'litre per minute bar',
        'L68' => 'litre per minute kelvin',
        'L69' => 'litre per hour bar',
        'L70' => 'litre per hour kelvin',
        'L71' => 'litre per day bar',
        'L72' => 'litre per day kelvin',
        'L73' => 'cubic metre per second pascal',
        'L74' => 'cubic metre per second kelvin',
        'L75' => 'cubic metre per minute pascal',
        'L76' => 'cubic metre per minute kelvin',
        'L77' => 'cubic metre per hour pascal',
        'L78' => 'cubic metre per hour kelvin',
        'L79' => 'cubic metre per day pascal',
        'L80' => 'cubic metre per day kelvin',
        'L81' => 'litre per second pascal',
        'L82' => 'litre per second kelvin',
        'L83' => 'litre per minute pascal',
        'L84' => 'litre per minute kelvin',
        'L85' => 'litre per hour pascal',
        'L86' => 'litre per hour kelvin',
        'L87' => 'litre per day pascal',
        'L88' => 'litre per day kelvin',
        'L89' => 'cubic metre per second bar',
        'L90' => 'cubic metre per minute bar',
        'L91' => 'cubic metre per hour bar',
        'L92' => 'cubic metre per day bar',
        'L93' => 'litre per second bar',
        'L94' => 'litre per minute bar',
        'L95' => 'litre per hour bar',
        'L96' => 'litre per day bar',
        'L98' => 'cubic metre per second pascal',
        'L99' => 'cubic metre per minute pascal',
        // Add more common unit codes as needed
    ];

    /**
     * Check if a unit code is valid
     *
     * @param string $code
     * @return bool
     */
    public static function isValid(string $code): bool
    {
        return isset(self::CODES[strtoupper($code)]);
    }

    /**
     * Get the description of a unit code
     *
     * @param string $code
     * @return string|null
     */
    public static function getDescription(string $code): ?string
    {
        return self::CODES[strtoupper($code)] ?? null;
    }

    /**
     * Get all valid unit codes
     *
     * @return array
     */
    public static function getAll(): array
    {
        return array_keys(self::CODES);
    }
}
