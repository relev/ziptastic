<?

//ini_set("display_errors", "1"); error_reporting(E_ALL ^ E_NOTICE);

require( 'adresator_db.php' ); // DB settings for db_connect()

function db_connect()
{
    $db = mysql_connect( DB_HOST, DB_USER, DB_PASS );
    mysql_select_db( DB_NAME );
}

function find_street( $street = false, $limit = 20 )
{
    $res = array();
    if ( $street )
    {
        db_connect();
        mysql_query( 'set group_concat_max_len=1024*1024' );
        $resource = mysql_query( "SELECT type_short, name, GROUP_CONCAT(DISTINCT CONCAT(region,',',city,',',zip,',',area) ORDER BY city_weight, zip SEPARATOR ';') AS localities FROM adresator_street LEFT JOIN PIndx07 USING(zip) WHERE name LIKE '$street%' GROUP BY type_short, name ORDER BY type_weight DESC, name ASC LIMIT $limit" );
#        $resource = mysql_query( "SELECT type_short, name, GROUP_CONCAT(DISTINCT CONCAT(region,',',city,',',zip,',',area) ORDER BY zip SEPARATOR ';') AS localities FROM PIndx07 LEFT JOIN adresator_street USING(zip) WHERE zip=$street GROUP BY type_short, name ORDER BY type_weight DESC, name ASC LIMIT 20" );
        if ( $resource )
        {
            while( $row = mysql_fetch_assoc($resource) )
            {
//                $row['localities'] = prepare_localities($row['localities']);
//                $row['type_short'] = prepare_type_short($row['type_short']);
                $res[] = $row;
            }
        }
    }
    return $res;
}

function find_zip( $zip = 0 )
{
    $zip = (int)$zip;

    $res = 0;
    if ( $zip > 0 )
    {
        db_connect();
        $resource = mysql_query( "SELECT zip, region, area, IF(city_1='',city,CONCAT(city,' (',city_1,')')) AS city FROM PIndx07 WHERE zip=$zip" );
        if ( $resource )
            $res = mysql_fetch_assoc( $resource );
    }
    return $res;
}

function mb_uc_first( $word, $need_lower_case = true )
{
    return mb_strtoupper( mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') 
           . mb_substr( $need_lower_case ? mb_convert_case($word, MB_CASE_LOWER, 'UTF-8') : $word, 1, mb_strlen($word), 'UTF-8');
}

function mb_uc_words( $string, $word_separators = ' ')
{   
    $string = mb_uc_first( $string ); 
    for ( $j = strlen( $word_separators ) - 1; $j >= 0; $j-- )
    {
        $words = mb_split( $word_separators[$j], $string );

        for ( $i = count($words)-1; $i >= 0; $i-- )
            $words[$i] = mb_uc_first( $words[$i], false );

        $string = implode( $word_separators[$j], $words );
    }
    return $string;
}

function prepare_type_short( $type_short )
{
    return ( mb_strlen($type_short, 'UTF-8') > 3 || mb_strpos( $type_short, '-' ) !== false )
        ? $type_short
        : $type_short.'.';
}

/*
function prepare_localities( $localities )
{
    $locality = mb_split( ';', $localities );

    for( $i = count($locality)-1; $i >= 0; $i-- )
        $locality[$i] = prepare_locality( $locality[$i] );

    return implode( ';', $locality );
}

function prepare_locality($locality)
{
    list( $region, $city, $zip ) = mb_split( ',', $locality );
    $region = prepare_region( $region );
    $city   = prepare_city( $city );
    return implode( ',', array( $region, $city, $zip ) );
}
*/

$FIX_REGIONS = array(
' Область' => ' область',
' Край' => ' край',
'якутия' => 'Якутия',
'балкарская' => 'Балкарская',
'черкесская' => 'Черкесская',
'алания' => 'Алания',
);

function prepare_region( $region )
{
    global $FIX_REGIONS, $SEEN;

    $r = mb_uc_words( $region );
    if ( mb_strpos( $r, 'Респу' ) !== false && mb_strpos( $r, 'ская ' ) === false )
    {
        $r = str_replace( ' Республика', '', $r );
        $r = str_replace( ' Республи', '', $r );
        $r = 'Республика '.$r;
    }

    foreach ( $FIX_REGIONS as $k => $v )
        $r = str_replace( $k, $v, $r );

    return $r;
}

function prepare_city( $city )
{
    $city = mb_uc_words( $city, ' -' );
    $city = str_replace( '-На-', '-на-', $city );
    return $city;
}

function array_compact( $array )
{
    foreach ( $array as $key => $val )
        if ( empty($val) )
            unset($array[$key]);

    return $array;
}

function fix_region_city()
{

$i = 100;

$cities = array(
'Москва'          => 10,
'Санкт-Петербург' => 20,
'Архангельск'   => $i++,
'Барнаул'       => $i++,
'Брянск'        => $i++,
'Владивосток'   => $i++,
'Волгоград'     => $i++,
'Воронеж'       => $i++,
'Екатеринбург'  => $i++,
'Ижевск'        => $i++,
'Иркутск'       => $i++,
'Казань'        => $i++,
'Кемерово'      => $i++,
'Краснодар'     => $i++,
'Красноярск'    => $i++,
'Львовский'     => $i++,
'Нижний Новгород' => $i++,
'Новосибирск'   => $i++,
'Омск'          => $i++,
'Пермь'         => $i++,
'Ростов-на-Дону'=> $i++,
'Самара'        => $i++,
'Саратов'       => $i++,
'Сочи'          => $i++,
'Тверь'         => $i++,
'Тюмень'        => $i++,
'Ульяновск'     => $i++,
'Уфа'           => $i++,
'Челябинск'     => $i++,
'Ярославль'     => $i++,
);

    db_connect();
// drop table PIndx07; create table PIndx07 like PIndx07_all; insert into PIndx07 select * from PIndx07_all; alter table PIndx07 add column city_weight int unsigned not null default 1000;
    $resource = mysql_query( 'SELECT * FROM PIndx07 WHERE region NOT LIKE "%а%"' );
    while( $row = mysql_fetch_assoc($resource) )
    {
        if ( !$row['city'] )
        {
            list( $region, $city ) = $row['region'] == 'МОСКВА'
                ? array( 'Московская область', 'Москва' )
                : array( 'Ленинградская область', 'Санкт-Петербург' );
        }
        else
        {
            $region = prepare_region($row['region']);
            $city   = prepare_city($row['city']);
        }
        $city_1 = $row['city_1'] ? prepare_city($row['city_1']) : '';
        $area   = $row['area']   ? mb_uc_first($row['area'])    : '';
        $city_weight = $cities[$city] ? $cities[$city] : 1000;
        mysql_query( sprintf( 'UPDATE PIndx07 SET region="%s", city="%s", city_1="%s", city_weight=%d, area="%s" WHERE zip=%d', $region, $city, $city_1, $city_weight, $area, $row['zip'] ) );
    }
}

function fix_type_short()
{
    db_connect();
    mysql_query( 'UPDATE adresator_street SET type_short = CONCAT(type_short,".") WHERE LENGTH(type_short)<15 AND type_short NOT LIKE "%-%" AND type_short NOT LIKE "%."' );
}

$query = addslashes( $_GET['q'] );
$limit = (int)$_GET['limit'];
if ( $limit < 2 || $limit > 50 )
    $limit = 20;

/** /
if ( true || $query == 'fix' )
{
    fix_region_city();
//    fix_type_short();
    print 'fix';
    exit;
}
/**/

mb_internal_encoding('UTF-8');
$query = mb_strtolower($query,'UTF-8');

$chars = array(
'q' => 'й',
'w' => 'ц',
'e' => 'у',
'r' => 'к',
't' => 'е',
'y' => 'н',
'u' => 'г',
'i' => 'ш',
'o' => 'щ',
'p' => 'з',
'[' => 'х',
']' => 'ъ',
'a' => 'ф',
's' => 'ы',
'd' => 'в',
'f' => 'а',
'g' => 'п',
'h' => 'р',
'j' => 'о',
'k' => 'л',
'l' => 'д',
';' => 'ж',
"'" => 'э',
'z' => 'я',
'x' => 'ч',
'c' => 'с',
'v' => 'м',
'b' => 'и',
'n' => 'т',
'm' => 'ь',
',' => 'б',
'.' => 'ю',
);

//if ( mb_ereg_match( '/[^а-яё]/' )
//$query = ' te,st '; 
//$query = mb_ereg_replace( '([^а-яё])', '1', $query, 'eg' );
//print $query;
if ( mb_ereg_match( '.*[^а-яё]', $query ) )
    foreach ( $chars as $k => $v )
        $query = str_replace( $k, $v, $query );

$res = preg_match('/^\d{6}$/', $query )
         ? find_zip( $query )
         : ( ( mb_strlen( $query ) < 2 || preg_match( '/\d.*\d.*\d/', $query ) ) 
            ? false
            : find_street( mb_uc_first($query), $limit ) );

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8', TRUE);

if ( !empty($res) )
{
//print_r($res); exit;
#    $json = json_encode(array( 'query' => $query, 'matches' => array_compact($res));
    $json = json_encode( array_compact($res) );
    $out = $_GET['callback'] 
              ? sprintf( '%s(%s);', $_GET['callback'], $json )
              : $json;
    echo $out;
}
else
{
    header( 'HTTP/1.0 404 Not Found' );
    echo '{}';
}

?>
