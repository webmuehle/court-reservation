<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.5.0
 * @package    Piramid
 * @subpackage Courtres/includes
 * @author     Webmühle e.U. <office@webmuehle.at>
 */

class Courtres_Entity_Base {

	protected static $instance = null;
	private $id;
	private static $cnt;
	public const LIMIT = 1000;
	private $db_data;

	public function __construct( $id ) {
		$this->id      = absint( $id );
		$this->db_data = self::get_by_id( $this->id );
	}

	public static function get_instance( $id ) {
		if ( ! empty( static::$instance ) ) {
			static::$instance = null;
		}
		static::$instance = new static( $id );
		return static::$instance;
	}

	public function get_id() {
		return $this->id;
	}

	/**
	 * @return array of data or empty array
	 */
	public function get_db_data() {
		return $this->db_data;
	}

	public static function get_charset_collate() {
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	// as in CourtresBase class
	protected function getTable( $table ) {
		global $wpdb;
		return "{$wpdb->prefix}courtres_{$table}";
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . static::$table_name;
	}

	public static function get_child_db_fields() {
		return static::get_db_fields();
	}

	public static function get_db_fields_defaults() {
		return wp_list_pluck( self::get_child_db_fields(), 'default_value' );
	}

	public static function get_admin_table_columns() {
		$db_fields = static::get_db_fields();
		$columns   = array();
		foreach ( $db_fields as $key => $db_field ) {
			if ( $db_field['show_in_admin'] ) {
				$columns[ $db_field['code'] ] = $db_field;
			}
		}
		return $columns;
	}

	static function is_dbtable_column( array $params ) {
		global $wpdb;
		$defaults = array(
			'name' => false,
		);
		$params   = wp_parse_args( $params, $defaults );
		if ( $params['name'] ) {
			$dbname    = $wpdb->dbname;
			$is_column = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `table_name` = %s AND `TABLE_SCHEMA` = %s AND `COLUMN_NAME` = %s",
					self::get_table_name(),
					$dbname,
					$params['name']
				),
				ARRAY_A
			);
			return $is_column;
		} else {
			return -1;
		}
	}

	static function add_dbtable_column( array $params ) {
		global $wpdb;
		$defaults = array(
			'name'  => false,
			'type'  => false,
			'after' => false,
		);
		$params   = wp_parse_args( $params, $defaults );
		if ( $params['name'] && $params['type'] && preg_match( '/^[a-zA-Z0-9_]+$/', $params['name'] ) && preg_match( '/^[a-zA-Z0-9_()\s,]+$/', $params['type'] ) ) {
			$dbname    = $wpdb->dbname;
			$is_column = self::is_dbtable_column( $params );
			if ( empty( $is_column ) ) {
				$after_sql = ( $params['after'] && preg_match( '/^[a-zA-Z0-9_]+$/', $params['after'] ) ) ? ' AFTER `' . $params['after'] . '`' : '';
				$table    = self::get_table_name();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL with regex-validated params
				$wpdb->query( "ALTER TABLE `$table` ADD `{$params['name']}` {$params['type']} NULL DEFAULT NULL" . $after_sql );
			}
		}
	}


	/**
	 * @return array $items or empty array
	 */
	static function get_list( $params = array() ) {
		global $wpdb;
		$defaults = array(
			'n_page'      => 0,
			'limit'       => self::LIMIT,
			'where'       => false,
			'sql_where'   => false,
			'sql_values'  => array(),
			'sort'        => self::get_table_name() . '.`id` DESC',
			'result_type' => 'ARRAY_A',
		);
		$params   = wp_parse_args( $params, $defaults );

		$limit  = (int) $params['limit'];
		$n_page = (int) $params['n_page'];
		$offset = ( $n_page ? $n_page - 1 : $n_page ) * $limit;
		if ( $offset < 0 ) {
			$offset = 0;
		}

		$sort     = self::sanitize_sort( $params['sort'] );
		$sql_sort = $sort ? ' ORDER BY ' . $sort : '';

		$where_spec = array();
		if ( ! empty( $params['sql_where'] ) ) {
			$where_spec['sql_where'] = $params['sql_where'];
			if ( isset( $params['sql_values'] ) && is_array( $params['sql_values'] ) ) {
				$where_spec['sql_values'] = $params['sql_values'];
			}
		} elseif ( ! empty( $params['where'] ) && is_array( $params['where'] ) ) {
			$where_spec = $params['where'];
			if ( ! empty( $where_spec['conditions'] ) && is_array( $where_spec['conditions'] ) ) {
				$where_spec['conditions'] = array_map( 'self::add_aliases', $where_spec['conditions'] );
			}
		}

		list( $sql_where, $where_values ) = self::compile_where( $where_spec );

		$sql_join        = '';
		$sql_select_more = '';
		$table           = self::get_table_name();
		$result_type     = self::sanitize_result_type( $params['result_type'] );

		$sql = 'SELECT ' . $table . '.*'
			. $sql_select_more
			. ' FROM ' . $table
			. $sql_join
			. $sql_where
			. $sql_sort
			. ' LIMIT %d OFFSET %d';

		$prepare_values = array_merge( $where_values, array( $limit, $offset ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is internal; values use placeholders
		if ( self::placeholder_count( $sql ) !== count( $prepare_values ) ) {
			self::$cnt = 0;
			return array();
		}
		$items = $wpdb->get_results(
			$wpdb->prepare( $sql, $prepare_values ),
			$result_type
		);

		$count_sql = 'SELECT COUNT(*) AS cnt FROM ' . $table . $sql_join . $sql_where;
		if ( $where_values ) {
			if ( self::placeholder_count( $count_sql ) !== count( $where_values ) ) {
				$res = null;
			} else {
				$res = $wpdb->get_row( $wpdb->prepare( $count_sql, $where_values ) );
			}
		} else {
			$res = $wpdb->get_row( $count_sql );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		self::$cnt = $res ? $res->cnt : 0;
		return $items ? $items : array();
	}


	/**
	 * get last query in get_list function
	 *
	 * @return int $cnt or 0
	 */
	static function get_last_query_length() {
		return self::$cnt;
	}


	/**
	 * get count of query
	 *
	 * @return int $cnt or 0
	 */
	static function count( $where = array() ) {
		global $wpdb;
		if ( ! is_array( $where ) ) {
			$where = array();
		}

		list( $sql_where, $where_values ) = self::compile_where( $where );
		$count_sql                        = 'SELECT COUNT(*) AS cnt FROM ' . self::get_table_name() . $sql_where;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is internal; values use placeholders
		if ( $where_values ) {
			if ( self::placeholder_count( $count_sql ) !== count( $where_values ) ) {
				return 0;
			}
			$res = $wpdb->get_row( $wpdb->prepare( $count_sql, $where_values ) );
		} else {
			$res = $wpdb->get_row( $count_sql );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$cnt = $res ? $res->cnt : 0;
		return $cnt;
	}


	/**
	 * @return array $item or empty array
	 */
	static function get_by_id( $id ) {
		$id    = absint( $id );
		$items = self::get_list(
			array(
				'where' => array(
					'conditions' => array(
						array(
							'sql'    => 'ID = %d',
							'values' => array( $id ),
						),
					),
				),
			)
		);
		return $items && is_array( $items ) ? $items[0] : array();
	}


	/**
	 * add aliases to sql queries
	 */
	static function add_aliases( $item ) {
		if ( is_array( $item ) ) {
			if ( isset( $item['sql'] ) && is_string( $item['sql'] ) ) {
				$item['sql'] = self::alias_sql_fragment( $item['sql'] );
			}
			return $item;
		}
		if ( ! is_string( $item ) ) {
			return $item;
		}
		return self::alias_sql_fragment( $item );
	}


	/**
	 * Prefix a simple column fragment with the entity table.
	 *
	 * Grouped fragments stay unchanged so an OR list is not prefixed as a whole.
	 *
	 * @param string $item SQL fragment.
	 * @return string
	 */
	protected static function alias_sql_fragment( $item ) {
		if ( false !== strpos( $item, '.' ) ) {
			return $item;
		}
		if ( preg_match( '/^\s*`?[A-Za-z_][A-Za-z0-9_]*`?\b/', $item ) ) {
			return self::get_table_name() . '.' . ltrim( $item );
		}
		return $item;
	}


	/**
	 * Build a WHERE fragment and placeholder values.
	 *
	 * A condition is either a comparison string or
	 * array( 'sql' => '`id` = %d', 'values' => array( 1 ) ).
	 * String fragments are read back into placeholders. A fragment that is not a
	 * simple comparison matches no rows.
	 *
	 * @param array $where conditions, logic, sql_where, sql_values.
	 * @return array
	 */
	protected static function compile_where( $where ) {
		if ( ! is_array( $where ) ) {
			return array( '', array() );
		}

		if ( ! empty( $where['sql_where'] ) && is_string( $where['sql_where'] ) ) {
			$values = ( isset( $where['sql_values'] ) && is_array( $where['sql_values'] ) ) ? array_values( $where['sql_values'] ) : array();
			$body   = preg_replace( '/^\s*WHERE\b\s*/i', '', $where['sql_where'] );
			if ( $values ) {
				if ( ! self::values_are_scalar( $values ) || self::placeholder_count( $body ) !== count( $values ) || ! self::template_is_safe( $body ) ) {
					return array( ' WHERE 1 = 0', array() );
				}
				return array( ' WHERE ' . $body, $values );
			}
			$compiled = self::parse_expression( $body );
			if ( null === $compiled ) {
				return array( ' WHERE 1 = 0', array() );
			}
			return array( ' WHERE ' . $compiled['sql'], $compiled['values'] );
		}

		if ( empty( $where['conditions'] ) || ! is_array( $where['conditions'] ) ) {
			return array( '', array() );
		}

		$logic = self::sanitize_logic( isset( $where['logic'] ) ? $where['logic'] : 'AND' );
		$parts = array();
		$values = array();
		foreach ( $where['conditions'] as $condition ) {
			$compiled = self::compile_condition( $condition );
			if ( null === $compiled ) {
				return array( ' WHERE 1 = 0', array() );
			}
			$parts[] = $compiled['sql'];
			$values  = array_merge( $values, $compiled['values'] );
		}

		return array( ' WHERE ' . implode( ' ' . $logic . ' ', $parts ), $values );
	}


	/**
	 * @param mixed $condition Condition string or sql/values array.
	 * @return array|null
	 */
	protected static function compile_condition( $condition ) {
		if ( is_array( $condition ) && isset( $condition['sql'] ) && is_string( $condition['sql'] ) ) {
			$values = ( isset( $condition['values'] ) && is_array( $condition['values'] ) ) ? array_values( $condition['values'] ) : array();
			if ( ! self::values_are_scalar( $values ) ) {
				return null;
			}
			if ( 0 === self::placeholder_count( $condition['sql'] ) ) {
				return self::parse_expression( $condition['sql'] );
			}
			if ( self::placeholder_count( $condition['sql'] ) !== count( $values ) || ! self::template_is_safe( $condition['sql'] ) ) {
				return null;
			}
			return array(
				'sql'    => $condition['sql'],
				'values' => $values,
			);
		}
		if ( ! is_string( $condition ) ) {
			return null;
		}
		return self::parse_expression( $condition );
	}


	/**
	 * @param string $sql SQL fragment with placeholders.
	 * @return bool
	 */
	protected static function template_is_safe( $sql ) {
		$probe = preg_replace( '/(?<!%)%(?:\d+\$)?d/i', '0', $sql );
		$probe = preg_replace( '/(?<!%)%(?:\d+\$)?[sfi]/i', "'x'", $probe );
		return null !== self::parse_expression( $probe );
	}


	/**
	 * @param array $values Placeholder values.
	 * @return bool
	 */
	protected static function values_are_scalar( $values ) {
		foreach ( $values as $value ) {
			if ( null !== $value && ! is_scalar( $value ) ) {
				return false;
			}
		}
		return true;
	}


	/**
	 * @param string $sql SQL fragment.
	 * @return int
	 */
	protected static function placeholder_count( $sql ) {
		$stripped = str_replace( '%%', '', $sql );
		preg_match_all( '/%(?:\d+\$)?[dsfi]/i', $stripped, $matches );
		return count( $matches[0] );
	}


	/**
	 * @param mixed $logic Join keyword.
	 * @return string
	 */
	protected static function sanitize_logic( $logic ) {
		if ( is_string( $logic ) && preg_match( '/^(AND|OR)$/i', $logic ) ) {
			return strtoupper( $logic );
		}
		return 'AND';
	}


	/**
	 * Keep column sorts. Anything else falls back to the id column.
	 *
	 * @param mixed $sort Sort clause.
	 * @return string
	 */
	protected static function sanitize_sort( $sort ) {
		$default = self::get_table_name() . '.`id` DESC';
		if ( ! is_string( $sort ) || '' === trim( $sort ) ) {
			return '';
		}
		$sort   = trim( $sort );
		$table  = preg_quote( self::get_table_name(), '/' );
		$column = '`?[A-Za-z_][A-Za-z0-9_]*`?';
		$piece  = '(?:(?:' . $table . ')\.)?' . $column . '(?:\s+(?:ASC|DESC))?';
		if ( ! preg_match( '/^' . $piece . '(?:\s*,\s*' . $piece . ')*$/i', $sort ) ) {
			return $default;
		}
		return $sort;
	}


	/**
	 * @param mixed $result_type Requested result shape.
	 * @return string
	 */
	protected static function sanitize_result_type( $result_type ) {
		$allowed = array( ARRAY_A, ARRAY_N, OBJECT, OBJECT_K );
		if ( in_array( $result_type, $allowed, true ) ) {
			return $result_type;
		}
		return ARRAY_A;
	}


	/**
	 * @param string $sql Expression.
	 * @return array|null
	 */
	protected static function parse_expression( $sql ) {
		if ( ! is_string( $sql ) ) {
			return null;
		}
		$parsed = self::parse_or_expr( $sql, 0 );
		if ( null === $parsed ) {
			return null;
		}
		if ( '' !== trim( substr( $sql, $parsed['pos'] ) ) ) {
			return null;
		}
		return array(
			'sql'    => $parsed['sql'],
			'values' => $parsed['values'],
		);
	}


	/**
	 * @param string $sql Expression.
	 * @param int    $pos Offset.
	 * @return array|null
	 */
	protected static function parse_or_expr( $sql, $pos ) {
		$left = self::parse_and_expr( $sql, $pos );
		if ( null === $left ) {
			return null;
		}
		while ( preg_match( '/^\s+OR\b/i', substr( $sql, $left['pos'] ), $match ) ) {
			$right = self::parse_and_expr( $sql, $left['pos'] + strlen( $match[0] ) );
			if ( null === $right ) {
				return null;
			}
			$left = array(
				'sql'    => $left['sql'] . ' OR ' . $right['sql'],
				'values' => array_merge( $left['values'], $right['values'] ),
				'pos'    => $right['pos'],
			);
		}
		return $left;
	}


	/**
	 * @param string $sql Expression.
	 * @param int    $pos Offset.
	 * @return array|null
	 */
	protected static function parse_and_expr( $sql, $pos ) {
		$left = self::parse_primary( $sql, $pos );
		if ( null === $left ) {
			return null;
		}
		while ( preg_match( '/^\s+AND\b/i', substr( $sql, $left['pos'] ), $match ) ) {
			$right = self::parse_primary( $sql, $left['pos'] + strlen( $match[0] ) );
			if ( null === $right ) {
				return null;
			}
			$left = array(
				'sql'    => $left['sql'] . ' AND ' . $right['sql'],
				'values' => array_merge( $left['values'], $right['values'] ),
				'pos'    => $right['pos'],
			);
		}
		return $left;
	}


	/**
	 * @param string $sql Expression.
	 * @param int    $pos Offset.
	 * @return array|null
	 */
	protected static function parse_primary( $sql, $pos ) {
		if ( preg_match( '/^\s*\(/', substr( $sql, $pos ), $match ) ) {
			$inner = self::parse_or_expr( $sql, $pos + strlen( $match[0] ) );
			if ( null === $inner ) {
				return null;
			}
			if ( ! preg_match( '/^\s*\)/', substr( $sql, $inner['pos'] ), $close ) ) {
				return null;
			}
			return array(
				'sql'    => '(' . $inner['sql'] . ')',
				'values' => $inner['values'],
				'pos'    => $inner['pos'] + strlen( $close[0] ),
			);
		}
		return self::parse_predicate( $sql, $pos );
	}


	/**
	 * @param string $sql Expression.
	 * @param int    $pos Offset.
	 * @return array|null
	 */
	protected static function parse_predicate( $sql, $pos ) {
		$ident = self::match_identifier( substr( $sql, $pos ) );
		if ( null === $ident ) {
			return null;
		}
		$cursor = $pos + $ident['len'];
		$after  = substr( $sql, $cursor );

		if ( preg_match( '/^\s+IN\s*\(/i', $after, $in_match ) ) {
			$list = self::parse_literal_list( $sql, $cursor + strlen( $in_match[0] ) );
			if ( null === $list || ! preg_match( '/^\s*\)/', substr( $sql, $list['pos'] ), $close ) ) {
				return null;
			}
			return array(
				'sql'    => $ident['sql'] . ' IN (' . implode( ', ', $list['placeholders'] ) . ')',
				'values' => $list['values'],
				'pos'    => $list['pos'] + strlen( $close[0] ),
			);
		}

		if ( ! preg_match( '/^\s*(!=|<>|<=|>=|=|<|>)\s*/', $after, $op_match ) ) {
			return null;
		}
		$cursor  += strlen( $op_match[0] );
		$after_op = substr( $sql, $cursor );
		if ( preg_match( '/^CURRENT_TIMESTAMP\(\)\s*-\s*INTERVAL\s+(\d+)\s+SECOND\b/i', $after_op, $interval ) ) {
			return array(
				'sql'    => $ident['sql'] . ' ' . $op_match[1] . ' CURRENT_TIMESTAMP() - INTERVAL %d SECOND',
				'values' => array( (int) $interval[1] ),
				'pos'    => $cursor + strlen( $interval[0] ),
			);
		}

		$literal = self::match_literal( $after_op );
		if ( null === $literal ) {
			return null;
		}
		return array(
			'sql'    => $ident['sql'] . ' ' . $op_match[1] . ' ' . $literal['placeholder'],
			'values' => array( $literal['value'] ),
			'pos'    => $cursor + $literal['len'],
		);
	}


	/**
	 * @param string $rest Remaining SQL.
	 * @return array|null
	 */
	protected static function match_identifier( $rest ) {
		if ( ! preg_match( '/^\s*(`?)([A-Za-z_][A-Za-z0-9_]*)\1(?:\s*\.\s*(`?)([A-Za-z_][A-Za-z0-9_]*)\3)?/', $rest, $match ) ) {
			return null;
		}
		if ( ! empty( $match[4] ) ) {
			$sql = '`' . $match[2] . '`.`' . $match[4] . '`';
		} else {
			$sql = '`' . $match[2] . '`';
		}
		return array(
			'sql' => $sql,
			'len' => strlen( $match[0] ),
		);
	}


	/**
	 * @param string $rest Remaining SQL.
	 * @return array|null
	 */
	protected static function match_literal( $rest ) {
		if ( preg_match( '/^(-?\d+)/', $rest, $match ) ) {
			return array(
				'placeholder' => '%d',
				'value'       => (int) $match[1],
				'len'         => strlen( $match[0] ),
			);
		}
		if ( preg_match( "/^'([^']*)'/", $rest, $match ) ) {
			return array(
				'placeholder' => '%s',
				'value'       => $match[1],
				'len'         => strlen( $match[0] ),
			);
		}
		if ( preg_match( '/^"([^"]*)"/', $rest, $match ) ) {
			return array(
				'placeholder' => '%s',
				'value'       => $match[1],
				'len'         => strlen( $match[0] ),
			);
		}
		return null;
	}


	/**
	 * @param string $sql Expression.
	 * @param int    $pos Offset.
	 * @return array|null
	 */
	protected static function parse_literal_list( $sql, $pos ) {
		$placeholders = array();
		$values       = array();
		$length       = strlen( $sql );
		$expect_comma = false;

		while ( $pos < $length ) {
			if ( preg_match( '/^\s+/', substr( $sql, $pos ), $ws ) ) {
				$pos += strlen( $ws[0] );
			}
			if ( $expect_comma ) {
				if ( ! preg_match( '/^,/', substr( $sql, $pos ) ) ) {
					break;
				}
				$pos += 1;
				if ( preg_match( '/^\s+/', substr( $sql, $pos ), $ws_after ) ) {
					$pos += strlen( $ws_after[0] );
				}
			}
			$literal = self::match_literal( substr( $sql, $pos ) );
			if ( null === $literal ) {
				if ( $expect_comma && preg_match( '/^,/', substr( $sql, $pos - 1 ) ) ) {
					return null;
				}
				if ( ! $placeholders ) {
					return null;
				}
				break;
			}
			$placeholders[] = $literal['placeholder'];
			$values[]       = $literal['value'];
			$pos           += $literal['len'];
			$expect_comma   = true;
		}

		if ( ! $placeholders ) {
			return null;
		}
		return array(
			'placeholders' => $placeholders,
			'values'       => $values,
			'pos'          => $pos,
		);
	}


	/**
	 *  Add
	 *
	 *  @param array with keys = db fields,
	 * @return id of inserted row or false
	 */
	static function insert( array $args ) {
		global $wpdb;

		$defaults = self::get_db_fields_defaults();
		$args     = wp_parse_args( $args, $defaults );
		$args     = array_intersect_key( $args, $defaults );

		$res = $wpdb->insert( self::get_table_name(), $args );
		if ( $res ) {
			$id = $wpdb->insert_id;
			return $id;
		} else {
			return false;
		}
	}


	/**
	 *  Update
	 *
	 *  @param array with keys = data, where, format, where_format
	 *  look $wpdb->update() params for details
	 * @return true||false
	 */
	static function update( array $args ) {
		global $wpdb;

		$defaults     = array(
			'data'         => false,
			'where'        => false,
			'format'       => false,
			'where_format' => false,
		);
		$args         = wp_parse_args( $args, $defaults );
		$args['data'] = array_intersect_key( $args['data'], self::get_db_fields_defaults() );

		$res = $wpdb->update( self::get_table_name(), $args['data'], $args['where'], $args['format'], $args['where_format'] );
		if ( $res !== false ) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 *  Delete rows
	 *
	 * @return true||false
	 */
	static function delete( array $args, $logic = 'OR' ) {
		global $wpdb;
		$result = false;
		$values = array();
		$sql    = '';

		if ( $args ) {
			if ( isset( $args['where'] ) && is_array( $args['where'] ) ) {
				$where = $args['where'];
				if ( empty( $where['logic'] ) ) {
					$where['logic'] = 'AND';
				}
				list( $sql_where, $values ) = self::compile_where( $where );
				if ( '' === $sql_where ) {
					return false;
				}
				$sql = 'DELETE FROM `' . self::get_table_name() . '`' . $sql_where;
			} else {
				$logic_sql = self::sanitize_logic( $logic );
				$parts     = array();
				foreach ( $args as $key => $value ) {
					if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
						return false;
					}
					if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) ) ) {
						$parts[]  = '(`' . $key . '` = %d)';
						$values[] = (int) $value;
					} else {
						$parts[]  = '(`' . $key . '` = %s)';
						$values[] = (string) $value;
					}
				}
				if ( ! $parts ) {
					return false;
				}
				$sql = 'DELETE FROM `' . self::get_table_name() . '` WHERE (' . implode( ' ' . $logic_sql . ' ', $parts ) . ')';
			}

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is internal; values use placeholders
			if ( $values ) {
				if ( self::placeholder_count( $sql ) !== count( $values ) ) {
					return false;
				}
				$prepared = $wpdb->prepare( $sql, $values );
			} else {
				$prepared = $sql;
			}
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
			if ( is_string( $prepared ) && '' !== $prepared ) {
				$res = $wpdb->query( $prepared );
				if ( $res ) {
					$result = true;
				}
			}
		}
		return $result;
	}


	/**
	 * Delete by id
	 *
	 * @return true||false
	 */
	public function delete_by_id() {
		return self::delete( array( 'id' => $this->get_id() ) );
	}


	/**
	 * Delete by id
	 *
	 * @return true||false
	 */
	public static function static_delete_by_id( int $id ) {
		return self::delete( array( 'id' => $id ) );
	}


	/**
	 * Get setting "Half-hour reservation"
	 *
	 * @return bool
	 */
	public function ishalfhour() {
		global $wpdb;
		$table_name       = $this->getTable( 'settings' );
		$option_half_hour = $wpdb->get_row( "SELECT * FROM $table_name WHERE option_name = 'half_hour_reservation'" );
		if ( ! isset( $option_half_hour ) ) {
			return false;
		}
		return $option_half_hour->option_value === '1' ? true : false;
	}


}
