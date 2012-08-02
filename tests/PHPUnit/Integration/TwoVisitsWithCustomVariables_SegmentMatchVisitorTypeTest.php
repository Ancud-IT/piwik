<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id$
 */

require_once dirname(__FILE__) . '/TwoVisitsWithCustomVariablesTest.php';

/**
 * Tests use of custom variable segments.
 */
class Test_Piwik_Integration_TwoVisitsWithCustomVariables_SegmentMatchVisitorType extends Test_Piwik_Integration_TwoVisitsWithCustomVariables
{
    public static function setUpBeforeClass()
    {
        IntegrationTestCase::setUpBeforeClass();
        self::$visitorId = substr(md5(uniqid()), 0, 16);
        self::$doExtraQuoteTests = false;
        try {
            self::setUpWebsitesAndGoals();
            self::trackVisits();
        } catch(Exception $e) {
            // Skip whole test suite if an error occurs while setup
            throw new PHPUnit_Framework_SkippedTestSuiteError($e->getMessage());
        }
    }

    /**
     * @dataProvider getApiForTesting
     * @group        Integration
     * @group        TwoVisitsWithCustomVariables_SegmentMatchVisitorType
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        // Segment matching some
        $segments = array('customVariableName1==VisitorType;customVariableValue1==LoggedIn',
            'customVariableName1==VisitorType;customVariableValue1=@LoggedI');

        $apiToCall = array('Referers.getKeywords', 'CustomVariables.getCustomVariables', 'VisitsSummary.get');

        $periods = array('day', 'week');

        // We run it twice just to check that running archiving twice for same input parameters doesn't create more records/overhead
        $result = array();
        for ($i = 1; $i <= 2; $i++) {
            foreach ($segments as $segment) {
                $result[] = array(
                    $apiToCall, array('idSite'       => 'all',
                                      'date'         => self::$dateTime,
                                      'periods'      => $periods,
                                      'setDateLastN' => true,
                                      'segment'      => $segment)
                );
            }
        }

        return $result;
    }

    /**
     * @depends      testApi
     * @group        Integration
     * @group        TwoVisitsWithCustomVariables_SegmentMatchVisitorType
     */
    public function testCheck()
    {
        // ----------------------------------------------
        // Implementation Checks
        // ----------------------------------------------
        // Verify that, when a segment is specified, only the requested report is processed
        // In this case, check that only the Custom Variables blobs have been processed

        if (IntegrationTestCase::$apiTestingLevel != IntegrationTestCase::NO_API_TESTING) {
            $tests = array(
                // 1) CHECK 'day' archive stored in January
                // We expect 2 segments * (1 custom variable name + 2 ref metrics + 6 subtable for the custom var values + 5 Referers blob)
                'archive_blob_2010_01'    => 28,
                // This contains all 'last N' weeks & days,
                // (1 metrics
                //  + 2 referer metrics
                //  + 3 done flag )
                //  * 2 segments
                // + 1 Done flag per Plugin, for each "Last N" date
                'archive_numeric_2010_01' => 138,

                // 2) CHECK 'week' archive stored in December (week starts the month before)
                // We expect 2 segments * (1 custom variable name + 2 ref metrics + 6 subtable for the values of the name + 5 referers blob)
                'archive_blob_2009_12'    => 28,
                // 6 metrics,
                // 2 Referer metrics (Referers_distinctSearchEngines/Referers_distinctKeywords),
                // 3 done flag (referers, CustomVar, VisitsSummary),
                // X * 2 segments
                'archive_numeric_2009_12' => (6 + 2 + 3) * 2,
            );
            foreach ($tests as $table => $expectedRows) {
                $sql        = "SELECT count(*) FROM " . Piwik_Common::prefixTable($table);
                $countBlobs = Zend_Registry::get('db')->fetchOne($sql);
                $this->assertEquals($expectedRows, $countBlobs, "$table: %s");
            }
        }
    }

    public function getOutputPrefix()
    {
        return 'twoVisitsWithCustomVariables_segmentMatchVisitorType';
    }
}
