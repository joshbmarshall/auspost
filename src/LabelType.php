<?php

namespace Cognito\Auspost;

/**
 * Definition of a Parcel Label for printing
 *
 * @package Cognito\Auspost
 * @author Josh Marshall <josh@jmarshall.com.au>
 *
 * @property string $layout_type
 * @property string $format
 * @property int $left_offset
 * @property int $top_offset
 * @property bool $branded
 */
class LabelType {

    const LAYOUT_A4_ONE_PER_PAGE   = 'A4-1pp';
    const LAYOUT_A4_TWO_PER_PAGE   = 'A4-2pp';
    const LAYOUT_A4_THREE_PER_PAGE = 'A4-3pp';
    const LAYOUT_A4_FOUR_PER_PAGE  = 'A4-4pp';
    const LAYOUT_A6_ONE_PER_PAGE   = 'A6-1PP';
    const LAYOUT_A6_THERMAL        = 'THERMAL-LABEL-A6-1PP';

    const FORMAT_PDF = 'PDF';
    const FORMAT_ZPL = 'ZPL';

    public $layout_type = self::LAYOUT_A4_ONE_PER_PAGE;
    public $format      = self::FORMAT_PDF;
    public $branded     = true;
    public $left_offset = 0;
    public $top_offset  = 0;

    public function __construct($details) {
        foreach ($details as $key => $data) {
            $this->$key = $data;
        }
    }
}
