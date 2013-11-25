<?php

namespace Test\TestBundle\XmlText;

use eZ\Publish\Core\FieldType\XmlText\Converter;
use DOMDocument;

class markdownConverter implements Converter
{
    protected $mdParser = null;

    public function __construct( $parser )
    {
        $this->mdParser = $parser;
    }

    /**
     * Does a partial conversion work on $xmlDoc.
     *
     * @param \DOMDocument $xmlDoc
     *
     * @return null
     */
    public function convert( DOMDocument $xmlDoc )
    {
        foreach ( $xmlDoc->getElementsByTagName( "literal" ) as $literal )
        {
            $code = $literal->getAttributeNS( "http://ez.no/namespaces/ezpublish3/custom/", 'lang' );
            $literalString = $literal->nodeValue;

            if ( $code == "markdown" )
            {
                // Convert Github markdown flavored before converting everything ;)
                // dirty but works !
                $pattern = "/`{3}([a-zA-Z]{0,9})\n(.*)`{3}/sU";
                preg_match_all( $pattern, $literalString, $matches );

                if ( count( $matches ) > 1 ) {
                    for( $i=0; $i < count( $matches[0] ); $i++ )
                    {
                        $codeLang = $matches[1][$i];
                        $codeScript = $matches[2][$i];

                        $highlight = $this->highlight( $codeScript, $codeLang );
                        $literalString = str_replace( $matches[0][$i], $highlight, $literalString );
                    }
                }

                $html = $this->mdParser->transformMarkdown( $literalString );
                $literal->nodeValue = htmlentities( $html );
            }
        }
    }

    /**
     * Convert a gfm to html
     *
     * @param $string
     * @param string $lexer
     * @param string $format
     * @return bool|string
     */
    protected function highlight($string, $lexer = 'bash', $format = 'html')
    {
        // use proc open to start pygmentize
        $descriptorspec = array(
            array( "pipe", "r" ), // stdin
            array( "pipe", "w" ), // stdout
            array( "pipe", "w" ), // stderr
        );

        $cwd = dirname( __FILE__ );
        $env = array();

        //if ( $extra_opts == "" )
        {
            $extra_opts = "-O style=default"; //,linenos=inline";
            if ( $lexer == "php" ) $extra_opts .= ",startinline=True";
            // } else {
            // Just append these to the passed-in args:
            // $extra_opts .= ",full,style=".$style.",cssclass=".$highlight_class;
            // }

            $proc = proc_open( '/usr/local/bin/pygmentize -l ' . $lexer . ' -f ' . $format . " " . $extra_opts, $descriptorspec, $pipes, $cwd, $env );

            if( !is_resource( $proc ) )
            {
                return false;
            }

            // now write $string to pygmentize's input
            fwrite( $pipes[0], $string );
            fclose( $pipes[0] );

            // the result should be available on stdout
            $result = stream_get_contents( $pipes[1] );
            fclose( $pipes[1] );

            // we don't care about stderr in this example
            // just checking the return val of the cmd
            $return_val = proc_close( $proc );

            if( $return_val !== 0 )
            {
                return false;
            }

            return $result;
        }
    }
}