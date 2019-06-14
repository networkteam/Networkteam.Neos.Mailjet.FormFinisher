<?php
namespace Networkteam\Neos\Mailjet\FormFinisher\Form\Finisher;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\I18n\Service;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\FluidAdaptor\View\StandaloneView;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Form\Exception\FinisherException;
use Neos\SwiftMailer\Message as SwiftMailerMessage;
use Neos\Utility\ObjectAccess;
use Neos\Flow\Annotations as Flow;

class MailjetFinisher extends \Neos\Form\Core\Model\AbstractFinisher
{
    /**
     * @var Service
     * @Flow\Inject
     */
    protected $i18nService;

    /**
     * @var array
     */
    protected $defaultOptions = array(
        'recipientName' => '',
        'senderName' => '',
        'attachAllPersistentResources' => false,
        'attachments' => [],
        'testMode' => false,
        'templateId' => null,
        'errorReportingRecipient' => null,
        'smtpUser' => null,
        'smtpPassword' => null,
        'smtpHost' => 'in-v3.mailjet.com',
        'smtpPort' => 587
    );

    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @return void
     * @throws FinisherException
     */
    protected function executeInternal()
    {
        if (!class_exists(SwiftMailerMessage::class)) {
            throw new FinisherException('The "neos/swiftmailer" doesn\'t seem to be installed, but is required for the EmailFinisher to work!', 1503392532);
        }
        $formRuntime = $this->finisherContext->getFormRuntime();
        $message = json_encode($formRuntime->getFormState()->getFormValues(), JSON_PRETTY_PRINT);

        $templateId = $this->parseOption('templateId');
        $errorReportingRecipient = $this->parseOption('errorReportingRecipient');
        $subject = $this->parseOption('subject');
        $recipientAddress = $this->parseOption('recipientAddress');
        $recipientName = $this->parseOption('recipientName');
        $senderAddress = $this->parseOption('senderAddress');
        $senderName = $this->parseOption('senderName');
        $replyToAddress = $this->parseOption('replyToAddress');
        $carbonCopyAddress = $this->parseOption('carbonCopyAddress');
        $blindCarbonCopyAddress = $this->parseOption('blindCarbonCopyAddress');
        $testMode = $this->parseOption('testMode');
        $smtpUser = $this->parseOption('smtpUser');
        $smtpPassword = $this->parseOption('smtpPassword');

        if ($subject === null) {
            throw new FinisherException('The option "subject" must be set for the EmailFinisher.', 1327060320);
        }
        if ($recipientAddress === null) {
            throw new FinisherException('The option "recipientAddress" must be set for the EmailFinisher.', 1327060200);
        }
        if (is_array($recipientAddress) && $recipientName !== '') {
            throw new FinisherException('The option "recipientName" cannot be used with multiple recipients in the EmailFinisher.', 1483365977);
        }
        if ($senderAddress === null) {
            throw new FinisherException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210);
        }

        $mail = new SwiftMailerMessage();

        if ($smtpUser !== null && $smtpPassword !== null  ) {
            $tf = new \Neos\SwiftMailer\TransportFactory();
            $transport = $tf->create(\Swift_SmtpTransport::class, [
                'host' => $this->parseOption('smtpHost'),
                'port' => $this->parseOption('smtpPort'),
                'username' => $smtpUser,
                'password' => $smtpPassword,
            ]);
            $mailer = new \Neos\SwiftMailer\Mailer($transport);
            ObjectAccess::setProperty($mail, 'mailer', $mailer, true);
        }

        $mail->getHeaders()->addTextHeader('X-MJ-VARS', json_encode($formRuntime->getFormState()->getFormValues(), JSON_UNESCAPED_UNICODE));
        $mail->getHeaders()->addTextHeader('X-MJ-TEMPLATELANGUAGE', "1");
        if ($this->parseOption('errorReportingRecipient') !== '' ) {
            $mail->getHeaders()->addTextHeader('X-MJ-TEMPLATEERRORREPORTING', $errorReportingRecipient);
        }

        $mail->getHeaders()->addTextHeader('X-MJ-TEMPLATEID', $templateId);

        $mail
            ->setFrom(array($senderAddress => $senderName))
            ->setSubject($subject);

        if (is_array($recipientAddress)) {
            $mail->setTo($recipientAddress);
        } else {
            $mail->setTo(array($recipientAddress => $recipientName));
        }

        if ($replyToAddress !== null) {
            $mail->setReplyTo($replyToAddress);
        }

        if ($carbonCopyAddress !== null) {
            $mail->setCc($carbonCopyAddress);
        }

        if ($blindCarbonCopyAddress !== null) {
            $mail->setBcc($blindCarbonCopyAddress);
        }

        $mail->setBody($message, 'text/plain');

        $this->addAttachments($mail);

        if ($testMode === true) {
            \Neos\Flow\var_dump(
                array(
                    'sender' => array($senderAddress => $senderName),
                    'recipients' => is_array($recipientAddress) ? $recipientAddress : array($recipientAddress => $recipientName),
                    'replyToAddress' => $replyToAddress,
                    'carbonCopyAddress' => $carbonCopyAddress,
                    'blindCarbonCopyAddress' => $blindCarbonCopyAddress,
                    'message' => $message
                ),
                'E-Mail "' . $subject . '"'
            );
        } else {
            $mail->send();
        }
    }

    /**
     * @param SwiftMailerMessage $mail
     * @return void
     * @throws FinisherException
     */
    protected function addAttachments(SwiftMailerMessage $mail)
    {
        $formValues = $this->finisherContext->getFormValues();
        if ($this->parseOption('attachAllPersistentResources')) {
            foreach ($formValues as $formValue) {
                if ($formValue instanceof PersistentResource) {
                    $mail->attach(\Swift_Attachment::newInstance(stream_get_contents($formValue->getStream()), $formValue->getFilename(), $formValue->getMediaType()));
                }
            }
        }
        foreach ($this->parseOption('attachments') as $attachmentConfiguration) {
            if (isset($attachmentConfiguration['resource'])) {
                $mail->attach(\Swift_Attachment::fromPath($attachmentConfiguration['resource']));
                continue;
            }
            if (!isset($attachmentConfiguration['formElement'])) {
                throw new FinisherException('The "attachments" options need to specify a "resource" path or a "formElement" containing the resource to attach', 1503396636);
            }
            $resource = ObjectAccess::getPropertyPath($formValues, $attachmentConfiguration['formElement']);
            if (!$resource instanceof PersistentResource) {
                continue;
            }
            $mail->attach(\Swift_Attachment::newInstance(stream_get_contents($resource->getStream()), $resource->getFilename(), $resource->getMediaType()));
        }
    }
}
