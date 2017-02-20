<?php

namespace ZeusTest\Helpers;

class UploadFile
{
    protected $fileName;

    protected $formName;

    protected $content;

    /**
     * UploadFile constructor.
     * @param $fileName
     * @param $formName
     * @param $content
     */
    public function __construct($fileName, $formName, $content)
    {
        $this->fileName = $fileName;
        $this->formName = $formName;
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @return mixed
     */
    public function getFormName()
    {
        return $this->formName;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }
}