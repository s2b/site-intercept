<?php
declare(strict_types = 1);
namespace T3G\Tests;

use T3G\Intercept\BambooStatusInformation;
use T3G\Intercept\GerritInformer;
use T3G\Intercept\InterceptController;
use T3G\Intercept\Library\CurlBambooRequests;
use T3G\Intercept\SlackMessageParser;

class InterceptControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @return void
     */
    public function newBuildActionTriggersNewBuildViaCurl(){
        $changeUrl = 'https://review.typo3.org/#/c/48574/';
        $patchset = 3;
        $_GET['changeUrl'] = $changeUrl;
        $_GET['patchset'] = (string)$patchset;

        $requester = $this->prophesize(CurlBambooRequests::class);

        $interceptController = new InterceptController($requester->reveal());
        $interceptController->newBuildAction();

        $requester->triggerNewCoreBuild($changeUrl, $patchset)->shouldHaveBeenCalled();
    }

    /**
     * @test
     * @return void
     */
    public function postBuildActionVotesOnGerrit()
    {
        $buildKey = 'CORE-GTC-48';
        $buildStatusInformation = ['successful' => true];
        $slackMessageParser = $this->prophesize(SlackMessageParser::class);
        $bambooStatusInformation = $this->prophesize(BambooStatusInformation::class);
        $gerritInformer = $this->prophesize(GerritInformer::class);

        $slackMessageParser->parseMessage()->willReturn($buildKey);
        $bambooStatusInformation->transform($buildKey)->willReturn($buildStatusInformation);

        $interceptController = new InterceptController(
            null,
            $slackMessageParser->reveal(),
            $bambooStatusInformation->reveal(),
            $gerritInformer->reveal()
        );

        $interceptController->postBuildAction();
        $gerritInformer->voteOnGerrit($buildStatusInformation)->shouldHaveBeenCalled();
    }

}
