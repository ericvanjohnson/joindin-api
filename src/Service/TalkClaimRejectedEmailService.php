<?php

namespace Joindin\Api\Service;

use Joindin\Api\Model\TalkModel;

class TalkClaimRejectedEmailService extends BaseEmailService
{
    protected array $event;
    protected TalkModel $talk;
    protected string $website_url;

    public function __construct(array $config, array $recipients, array $event, TalkModel $talk)
    {
        // set up the common stuff first
        parent::__construct($config, $recipients);

        $this->talk  = $talk;
        $this->event = $event['events'][0];
    }

    public function sendEmail(): void
    {
        $this->setSubject("Joind.in: Your talk claim has been rejected");

        $replacements = [
            "eventName" => $this->event['name'],
            "talkTitle" => $this->talk->talk_title
        ];

        $messageBody = $this->parseEmail("talkClaimRejected.md", $replacements);
        $messageHTML = $this->markdownToHtml($messageBody);

        $this->setBody($this->htmlToPlainText($messageHTML));
        $this->setHtmlBody($messageHTML);

        $this->dispatchEmail();
    }
}
