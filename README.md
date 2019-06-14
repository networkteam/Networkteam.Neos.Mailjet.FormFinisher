# Neos Mailjet Form Finisher

This package provides an additional finisher to be used with [mailjet.com](http://mailjet.com) to send templated mails via their SMTP Api.

## Configuration

There are two ways to configure the sending of the mails by the mailjet system.
The first one is to use the general Neos/Flow way by configuring the NeosSwiftMailer 

```yaml
Neos:
 SwiftMailer:
    transport:
         type: 'Swift_SmtpTransport'
        options:
            host: 'in-v3.mailjet.com'
            port: 587
            username: '<MailjetUser>'
            password: '<MailjetPassword>'
      
```

All Values can be found in the Mailjet dashboard -> transactional mails -> SMTP

The second option is to use the node configuration on the mailjet finisher itself. 
This is useful if different departments want to use mailjet with different accounts, e.q. Marketing and Human Resources.

## Using the form values in the mailjet template

The configured fields in the form are populated as variables to the mailjet templating system. 
The variables are directly usable with {{var:<field_identifier>}}. To use this the identifier of the form field must be set.
When setting an errorReportingRecipient this one will get failure notices for errors occured while rendering the template by mailjet.