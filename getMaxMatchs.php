<?php

require_once('fetchUrl.php');

if (!(file_exists('matchs') && is_dir('matchs')))
  shell_exec('mkdir matchs');
if (!(file_exists('historys') && is_dir('historys')))
  shell_exec('mkdir historys');

// Settings - Filters

$apikey = trim(file_get_contents('steamapikey'));
$steamId = 109943; // main account laxa
// be carefull to use good date format according to your region configuration
/* $startTime = strtotime('04/01/2015'); */
/* $endTime = strtotime('12/12/2016'); */
$maxMatchToCount = 4000;
$top = 10;
$offline = false;
$debug = true;
$verbose = false;
// Possible values :
// -1 : invalid
// 0 : public matchmaking
// 1 : practice
// 2 : Tournament
// 3 : Tutorial
// 4 : Co-op with bots
// 5 : Team match
// 6 : Solo queue
// 7 : Ranked
// 8 : Solo Mid 1v1
$lobbyType = array(7);
// List of heroes to display stats about by their Id
$heroFilter = array(2, 95, 35, 8, 25);
// Sorts :
// $AvailableSorts = array('Total', 'Against', 'With', 'AgainstWin', 'AgainstLost', 'WithWin', 'WithLost');
$sort = 'Total';
// order possible values = desc or null
$order = 'desc';

// Script is starting here :
$numberMatchs = 0;
$failedFetch = 0;
echo "Checking match history size to choose fetching method.\n";
$json = fetchHistory();
// API limit return of 500 matchs history
if ($json['result']['total_results'] < 500)
  {
    echo 'Less than 500 matches to fetch, fetching them directly now!'."\n";
    fetchMatchsWithCriteria($json, $numberMatchs);
  }
else
  {
    echo 'More than 500 matches fo fetch, fetching by hero!'."\n";
    fetchMatchsByHero($numberMatchs);
  }
echo "Fetched $numberMatchs matchs!\n";
echo "Failed to fetch $failedFetch matchs!\n";
// END OF SCRIPT

function fetchMatchsWithCriteria($json, &$numberMatchs, $key = null)
{
  global $numberMatchs;

  do
    {
      if (isset($match))
	$json = fetchHistory($match['match_id'], $key);
      foreach ($json['result']['matches'] as $match)
	{
	  $matchId = $match['match_id'];
	  if (!file_exists("matchs/$matchId"))
	    {
	      fetchMatchDetails($matchId);
	      $numberMatchs++;
	    }
	}
    } while ($json['result']['results_remaining'] > 0);
}

function fetchMatchsByHero(&$numberMatchs)
{
  $heroList = loadHeroesList();

  foreach ($heroList as $key => $value)
    {
      echo "Fetching matchs with $value...\n";
      $json = fetchHistory(null, $key);
      echo $json['result']['total_results']." total results to fetch for that hero\n";
      fetchMatchsWithCriteria($json, $numberMatchs, $key);
    }
}

function fetchHistory($startId = null, $heroId = null)
{
  global $apikey;
  global $steamId;
  global $debug;

  $request = "https://api.steampowered.com/IDOTA2Match_570/GetMatchHistory/V001/?account_id=$steamId&key=$apikey";
  if ($heroId != null)
    $request .= "&hero_id=$heroId";
  if ($startId != null)
    $request .= "&start_at_match_id=$startId";
  if ($debug)
    echo $request."\n";
  echo "Fetching match history\n";
  $return = fetchUrl($request);
  file_put_contents('historys/'.$steamId, $return);
  $json = json_decode($return, true);
  if ($debug)
    echo $json['result']['num_results'].' results on '.$json['result']['total_results'].', '.$json['result']['results_remaining']." more results to fetch\n";
  return $json;
}

function fetchHeroesList()
{
  $apikey = trim(file_get_contents('steamapikey'));
  $request = "https://api.steampowered.com/IEconDOTA2_570/GetHeroes/v0001/?key=$apikey&language=en_us";
  $return = fetchUrl($request);
  file_put_contents('herolistjson', $return);
}

function fetchMatchDetails($id)
{
  global $failedFetch;
  global $apikey;

  echo "Fetching match $id...";
  $matchDetailRequest = "https://api.steampowered.com/IDOTA2Match_570/GetMatchDetails/V001/?match_id=$id&key=$apikey";
  //  $rawJson = file_get_contents($matchDetailRequest);
  $rawJson = fetchUrl($matchDetailRequest);
  if ($rawJson === false || strlen($rawJson) == 0)
    {
      echo 'An error occured while fetching match '.$id."\n";
      $failedFetch++;
      return array();
    }
  file_put_contents("matchs/$id", $rawJson);
  echo "Done\n";
}

function loadHeroesList()
{
  if (!file_exists('herolistjson'))
    fetchHeroesList();
  $heroList = file_get_contents('herolistjson');
  $json = json_decode($heroList, true);
  $heroList = array();
  foreach ($json['result']['heroes'] as $hero)
    $heroList[$hero['id']] = $hero['localized_name'];
  return $heroList;
}

?>
