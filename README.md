# SMS as XML Processor

## Background

I wanted to create a readable conversation of all of the text messages between me and one other person. The popular
Android SMS backup app, SMS Backup and Restore (http://android.riteshsahu.com/apps/sms-backup-restore)
is capable of saving a single conversation, including MMS and emojis, into an XML file. However, as the app warns, that 
XML will not be parseable by other readers. The reason is that the app stores the emojis using their NCR (see 
http://en.wikipedia.org/wiki/Numeric_character_reference), which will not validate as XML.

I developed two scripts. The first processes the source xml as text, and replaces all NCRs with XML safe strings. In the 
process, I convert the NCRs to their unicode values, which allows for easier graphical representation later on, as many 
emoji libraries use the unicode value to index the images.  As the xml can now validate as xml, the second script reads 
the xml using an actual xml library, performs some additional post processing (see notes in code), and outputs the 
messages into a basic html file.

## Requirements

You will need an image library of emojis. This script uses the images from the gemoji project (https://github.com/github/gemoji/) 
and assumes:

1) all images reside in images/emoji/unicode  
2) all images are PNGs  
3) all images are named with their unicode value, in lowercase (eg, 1f3aa.png)

## Instructions

NOTE: For (2), *me* is my name and *you* is the name of the person with whom I'm communicating. These names will be used 
to notate the final HTML output.

1) php convert.php *fileIn* *fileOut*  
2) php render.php *fileIn* *me* *you* &gt; *htmlFile*  
(or in browser: http://path/to/render.php?file={file}&amp;me={me}$amp;you={you})

## Acknowledgements

http://android.riteshsahu.com/apps/sms-backup-restore - For exporting SMS/MMS to XML
https://github.com/github/gemoji/ - For emoji images
http://php.net/manual/en/function.mb-split.php#80046 - For splitting mb strings into char arrays
http://www.endmemo.com/unicode/unicodeconverter.php - For web based conversion of multibyte characters
http://www.endmemo.com/unicode/script/convertuni.js - For code based conversion of multibyte characters
