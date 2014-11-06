<?php

namespace DataValues\Geo\Parsers;

use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\Geo\Values\LatLongValue;
use ValueParsers\ParseException;
use ValueParsers\ParserOptions;
use ValueParsers\StringValueParser;

/**
 * Extends the GeoCoordinateParser by adding precision detection support.
 *
 * The object that gets constructed is a GlobeCoordinateValue rather then a LatLongValue.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
class GlobeCoordinateParser extends StringValueParser {

	const FORMAT_NAME = 'coordinate';

	const OPT_GLOBE = 'globe';

	/**
	 * @param ParserOptions|null $options
	 */
	public function __construct( ParserOptions $options = null ) {
		parent::__construct( $options );

		$this->defaultOption( self::OPT_GLOBE, 'http://www.wikidata.org/entity/Q2' );
	}

	/**
	 * @see StringValueParser::stringParse
	 *
	 * @param string $value
	 *
	 * @return GlobeCoordinateValue
	 * @throws ParseException
	 */
	protected function stringParse( $value ) {
		foreach ( $this->getParsers() as $precisionDetector => $parser ) {
			try {
				$latLong = $parser->parse( $value );

				return new GlobeCoordinateValue(
					new LatLongValue(
						$latLong->getLatitude(),
						$latLong->getLongitude()
					),
					$this->detectPrecision( $latLong, $precisionDetector ),
					$this->options->getOption( 'globe' )
				);
			} catch ( ParseException $parseException ) {
				continue;
			}
		}

		throw new ParseException(
			'The format of the coordinate could not be determined.',
			$value,
			self::FORMAT_NAME
		);
	}

	private function detectPrecision( LatLongValue $latLong, $precisionDetector ) {
		if ( $this->options->hasOption( 'precision' ) ) {
			return $this->options->getOption( 'precision' );
		}

		return min(
			call_user_func( array( $this, $precisionDetector ), $latLong->getLatitude() ),
			call_user_func( array( $this, $precisionDetector ), $latLong->getLongitude() )
		);
	}

	/**
	 * @return  StringValueParser[]
	 */
	private function getParsers() {
		$parsers = array();

		$parsers['detectFloatPrecision'] = new FloatCoordinateParser( $this->options );
		$parsers['detectDmsPrecision'] = new DmsCoordinateParser( $this->options );
		$parsers['detectDmPrecision'] = new DmCoordinateParser( $this->options );
		$parsers['detectDdPrecision'] = new DdCoordinateParser( $this->options );

		return $parsers;
	}

	protected function detectDdPrecision( $degree ) {
		// TODO: Implement localized decimal separator.
		$split = explode( '.', $degree );

		$precision = 1;

		if ( isset( $split[1] ) ) {
			$precision = pow( 10, -strlen( $split[1] ) );
		}

		return $precision;
	}

	protected function detectDmPrecision( $degree ) {
		$minutes = $degree * 60;

		// Since arcminutes shall be used to detect the precision, precision needs at least to be an
		// arcminute:
		$precision = 1 / 60;

		// The minute may be a float; In order to detect a proper precision, we convert the minutes
		// to seconds.
		if ( $minutes - floor( $minutes ) > 0 ) {
			$seconds = $minutes * 60;

			$precision = 1 / 3600;

			// TODO: Implement localized decimal separator.
			$secondsSplit = explode( '.', $seconds );

			if ( isset( $secondsSplit[1] ) ) {
				$precision *= pow( 10, -strlen( $secondsSplit[1] ) );
			}
		}

		return $precision;
	}

	protected function detectDmsPrecision( $degree ) {
		$seconds = $degree * 3600;

		// Since arcseconds shall be used to detect the precision, precision needs at least to be an
		// arcsecond:
		$precision = 1 / 3600;

		if ( $degree - floor( $degree ) > 0 ) {
			// TODO: Implement localized decimal separator.
			$secondsSplit = explode( '.', $seconds );

			if ( isset( $secondsSplit[1] ) ) {
				$precision *= pow( 10, -strlen( $secondsSplit[1] ) );
			}
		}

		return $precision;
	}

	protected function detectFloatPrecision( $degree ) {
		// TODO: Implement localized decimal separator.
		$split = explode( '.', $degree );

		$precision = 1;

		if ( isset( $split[1] ) ) {
			$precision = pow( 10, -strlen( $split[1] ) );
		}

		return $precision;
	}

}
