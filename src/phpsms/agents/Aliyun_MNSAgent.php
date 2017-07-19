<?php

namespace Toplan\PhpSms;


class Constants
{
    const GMT_DATE_FORMAT = "D, d M Y H:i:s \\G\\M\\T";

    const MNS_VERSION_HEADER = "x-mns-version";
    const MNS_HEADER_PREFIX = "x-mns";
    const MNS_XML_NAMESPACE = "http://mns.aliyuncs.com/doc/v1/";

    const MNS_VERSION = "2015-06-06";
    const AUTHORIZATION = "Authorization";
    const MNS = "MNS";

    const CONTENT_LENGTH = "Content-Length";
    const CONTENT_TYPE = "Content-Type";
    const SECURITY_TOKEN = "security-token";
    const DIRECT_MAIL = "DirectMail";
    const DIRECT_SMS = "DirectSMS";
    const WEBSOCKET = "WebSocket";

    // XML Tag
    const ERROR = "Error";
    const ERRORS = "Errors";
    const DELAY_SECONDS = "DelaySeconds";
    const MAXIMUM_MESSAGE_SIZE = "MaximumMessageSize";
    const MESSAGE_RETENTION_PERIOD = "MessageRetentionPeriod";
    const VISIBILITY_TIMEOUT = "VisibilityTimeout";
    const POLLING_WAIT_SECONDS = "PollingWaitSeconds";
    const MESSAGE_BODY = "MessageBody";
    const PRIORITY = "Priority";
    const MESSAGE_ID = "MessageId";
    const MESSAGE_BODY_MD5 = "MessageBodyMD5";
    const ENQUEUE_TIME = "EnqueueTime";
    const NEXT_VISIBLE_TIME = "NextVisibleTime";
    const FIRST_DEQUEUE_TIME = "FirstDequeueTime";
    const RECEIPT_HANDLE = "ReceiptHandle";
    const RECEIPT_HANDLES = "ReceiptHandles";
    const DEQUEUE_COUNT = "DequeueCount";
    const ERROR_CODE = "ErrorCode";
    const ERROR_MESSAGE = "ErrorMessage";
    const ENDPOINT = "Endpoint";
    const STRATEGY = "NotifyStrategy";
    const CONTENT_FORMAT = "NotifyContentFormat";
    const LOGGING_BUCKET = "LoggingBucket";
    const LOGGING_ENABLED = "LoggingEnabled";
    const MESSAGE_ATTRIBUTES = "MessageAttributes";
    const SUBJECT = "Subject";
    const ACCOUNT_NAME = "AccountName";
    const ADDRESS_TYPE = "AddressType";
    const REPLY_TO_ADDRESS = "ReplyToAddress";
    const IS_HTML = "IsHtml";
    const FREE_SIGN_NAME = "FreeSignName";
    const TEMPLATE_CODE = "TemplateCode";
    const RECEIVER = "Receiver";
    const SMS_PARAMS = "SmsParams";
    const IMPORTANCE_LEVEL = "ImportanceLevel";

    // some MNS ErrorCodes
    const INVALID_ARGUMENT = "InvalidArgument";
    const QUEUE_ALREADY_EXIST = "QueueAlreadyExist";
    const QUEUE_NOT_EXIST = "QueueNotExist";
    const MALFORMED_XML = "MalformedXML";
    const MESSAGE_NOT_EXIST = "MessageNotExist";
    const RECEIPT_HANDLE_ERROR = "ReceiptHandleError";
    const BATCH_SEND_FAIL = "BatchSendFail";
    const BATCH_DELETE_FAIL = "BatchDeleteFail";

    const TOPIC_ALREADY_EXIST = "TopicAlreadyExist";
    const TOPIC_NOT_EXIST = "TopicNotExist";
    const SUBSCRIPTION_ALREADY_EXIST = "SubscriptionAlreadyExist";
    const SUBSCRIPTION_NOT_EXIST = "SubscriptionNotExist";
}


/**
 * Class AliyunAgent
 *
 * @property string $accessKeyId
 * @property string $accessKeySecret
 * @property string $signName
 * @property string $endPoint
 * @property string $topicName
 */
class Aliyun_MNSAgent extends Agent implements TemplateSms
{

    private $tempId, $smsParams;

    public function sendTemplateSms($to, $tempId, array $data)
    {
        $this->smsParams = [$to=>$data];
        $this->tempId = $tempId;
        $this->request();

    }

    protected function request()
    {
        $sendurl = $this->endPoint . '/topics/' . $this->topicName . '/messages';
        $result = $this->curlPost($sendurl, [], [
            CURLOPT_HTTPHEADER => $this->createHeaders(),
            CURLOPT_POSTFIELDS => $this->generateBody(),
        ]);
        $this->setResult($result);
    }

    protected function writeMessagePropertiesForPublishXML(\XMLWriter $xmlWriter)
    {

        $xmlWriter->writeElement(Constants::MESSAGE_BODY, 'smsmessage');
        $xmlWriter->startElement(Constants::MESSAGE_ATTRIBUTES);
        $jsonArray = array("Type" => "multiContent");
        $jsonArray[Constants::FREE_SIGN_NAME] = $this->signName;
        $jsonArray[Constants::TEMPLATE_CODE] = $this->tempId;

        if ($this->smsParams != null) {
            if (!is_array($this->smsParams)) {
                throw new PhpSmsException("SmsParams should be an array!",400);
            }
            if (!empty($this->smsParams)) {
                $jsonArray['SmsParams'] = json_encode($this->smsParams,JSON_FORCE_OBJECT);
            }
        }

        if (!empty($jsonArray)) {
            $xmlWriter->writeElement(Constants::DIRECT_SMS, json_encode($jsonArray));
        }
        $xmlWriter->endElement();
    }


    protected function generateBody()
    {
        $xmlWriter = new \XMLWriter;
        $xmlWriter->openMemory();
        $xmlWriter->startDocument("1.0", "UTF-8");
        $xmlWriter->startElementNS(NULL, "Message", Constants::MNS_XML_NAMESPACE);
        $this->writeMessagePropertiesForPublishXML($xmlWriter);
        $xmlWriter->endElement();
        $xmlWriter->endDocument();

      return $xmlWriter->outputMemory();
    }

    protected function createHeaders()
    {
        $params = [
            Constants::CONTENT_TYPE => 'text/xml',
            Constants::MNS_VERSION_HEADER => Constants::MNS_VERSION,
            'Date' => gmdate(Constants::GMT_DATE_FORMAT),
        ];
        $params[Constants::AUTHORIZATION] = Constants::MNS . " " . $this->accessKeyId . ":" . $this->computeSignature($params);

        $p = [];
        foreach ($params as $k => $v) {
            $p[] = "$k:$v";
        }
        return $p;
    }


    private function computeSignature($parameters)
    {
        $contentMd5 = '';
        $contentType = $parameters[Constants::CONTENT_TYPE];
        $date = $parameters['Date'];
        $canonicalizedMNSHeaders = Constants::MNS_VERSION_HEADER.':' . $parameters[Constants::MNS_VERSION_HEADER];
        $canonicalizedResource = '/topics/' . $this->topicName . '/messages';
        $stringToSign = strtoupper('POST') . "\n" . $contentMd5 . "\n" . $contentType . "\n" . $date . "\n" . $canonicalizedMNSHeaders . "\n" . $canonicalizedResource;

        return base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
    }


    protected function loadXmlContent($content)
    {
        $xmlReader = new \XMLReader();
        $isXml = $xmlReader->XML($content);
        if ($isXml === FALSE) {

        }
        try {
            while ($xmlReader->read()) {}
        } catch (\Exception $e) {

        }
        $xmlReader->XML($content);
        return $xmlReader;
    }

    protected function setResult($result)
    {

        if ($result['request']) {
            $this->result(Agent::INFO, $result['response']);


            $messageId = NULL;
            $messageBodyMD5 = NULL;
            $errorCode = NULL;
            $errorMessage = NULL;

            $xmlReader=  $this->loadXmlContent($result['response']);
            while ($xmlReader->read())
            {
                switch ($xmlReader->nodeType)
                {
                    case \XMLReader::ELEMENT:
                        switch ($xmlReader->name) {
                            case Constants::MESSAGE_ID:
                                $xmlReader->read();
                                if ($xmlReader->nodeType == \XMLReader::TEXT)
                                {
                                    $messageId = $xmlReader->value;
                                }
                                break;
                            case Constants::MESSAGE_BODY_MD5:
                                $xmlReader->read();
                                if ($xmlReader->nodeType == \XMLReader::TEXT)
                                {
                                    $messageBodyMD5 = $xmlReader->value;
                                }
                                break;
                            case Constants::ERROR_CODE:
                                $xmlReader->read();
                                if ($xmlReader->nodeType == \XMLReader::TEXT)
                                {
                                    $errorCode = $xmlReader->value;
                                }
                                break;
                            case Constants::ERROR_MESSAGE:
                                $xmlReader->read();
                                if ($xmlReader->nodeType == \XMLReader::TEXT)
                                {
                                    $errorMessage = $xmlReader->value;
                                }
                                break;
                        }
                        break;
                    case \XMLReader::END_ELEMENT:
                        if ($xmlReader->name == 'Message')
                        {
                            if ($messageId != NULL)
                            {
                                $this->result(Agent::SUCCESS, true);
                            }
                            else
                            {
                                $this->result(Agent::SUCCESS, false);
                            }
                        }
                        break;
                }
            }

            if ($messageId != NULL)
            {
                $this->result(Agent::SUCCESS, true);
            }
            else
            {
                $this->result(Agent::SUCCESS, false);
            }

            if ($errorCode!=NULL)
            $this->result(Agent::CODE, $errorCode);


        } else {
            $this->result(Agent::INFO, 'request failed');
        }
    }


}
