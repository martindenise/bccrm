<?php



/**
 * My Class for sending an email.
 *
 */
class MyLibs_Mail extends Zend_Mail
{

    /**
     * Clear To-header and recipient
     *
     */
    public function clearTo()
    {
        unset($this->_recipients);
        $this->_recipients = array();
    }
}
