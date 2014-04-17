<?php

namespace Sastrawi\Stemmer;

use Sastrawi\Dictionary\DictionaryInterface;

class Stemmer
{
    protected $dictionary;

    protected $visitors = array();

    protected $suffixVisitors = array();

    protected $prefixVisitors = array();

    public function __construct(DictionaryInterface $dictionary)
    {
        $this->dictionary = $dictionary;
        $this->initVisitors();
    }

    public function getDictionary()
    {
        return $this->dictionary;
    }

    protected function initVisitors()
    {
        $visitorProvider = new Visitor\VisitorProvider();

        $this->visitors       = $visitorProvider->getVisitors();
        $this->suffixVisitors = $visitorProvider->getSuffixVisitors();
        $this->prefixVisitors = $visitorProvider->getPrefixVisitors();
    }

    /**
     * Stem a sentence to common stem form of its words
     *
     * @param  string $sentence the sentence to stem, e.g : memberdayakan pembangunan
     * @return string common stem form, e.g : daya bangun
     */
    public function stem($sentence)
    {
        $filteredSentence = $this->filterSentence($sentence);

        $words = explode(' ', $filteredSentence);
        $stemmedWords = array();

        foreach ($words as $word) {
            $stemmedWords[] = $this->stemWord(strtolower($word));
        }

        return implode(' ', $stemmedWords);
    }

    /**
     * Stem a word to its common stem form
     *
     * @param  string $word the word to stem, e.g : mengalahkan
     * @return string common stem form, e.g : kalah
     */
    public function stemWord($word)
    {
        $context = new Context($word, $this->dictionary);

        if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
            return $context->getCurrentWord();
        }

        $this->acceptVisitors($context, $this->visitors);

        if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
            return $context->getCurrentWord();
        }

        $csPrecedenceAdjustmentSpecification = new CS\PrecedenceAdjustmentSpecification();

        if (! $csPrecedenceAdjustmentSpecification->isSatisfiedBy($word)) {

            $this->acceptVisitors($context, $this->suffixVisitors);
            if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
                return $context->getCurrentWord();
            }

            for ($i = 0; $i < 3; $i++) {
                $this->acceptVisitors($context, $this->prefixVisitors);
                if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
                    return $context->getCurrentWord();
                }
            }

        } else {

            for ($i = 0; $i < 3; $i++) {
                $this->acceptVisitors($context, $this->prefixVisitors);
                if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
                    return $context->getCurrentWord();
                }
            }

            $this->acceptVisitors($context, $this->suffixVisitors);
            if ($this->dictionary->lookup($context->getCurrentWord()) !== null) {
                return $context->getCurrentWord();
            }

        }

        if ($this->dictionary->lookup($context->getCurrentWord())) {
            return $context->getCurrentWord();
        } else {
            return $word;
        }
    }

    protected function acceptVisitors(ContextInterface $context, array $visitors)
    {
        foreach ($visitors as $visitor) {

            $context->accept($visitor);

            $lookupResult = $context->getDictionary()->lookup($context->getCurrentWord());
            if ($lookupResult !== null) {
                return $lookupResult;
            }

            if ($context->processIsStopped()) {
                return $context->getCurrentWord();
            }
        }
    }

    public function filterSentence($sentence)
    {
        $sentence = trim(str_replace('.', ' ', $sentence));

        return preg_replace('/[^a-z0-9 -]/im', '', $sentence);
    }

    /**
     * Does the word contain invalid affix pair?
     * ber-i|di-an|ke-i|ke-an|me-an|ter-an|per-an
     * @param string $word
     */
    public function containsInvalidAffixPair($word)
    {
        if (preg_match('/^me(.*)kan$/', $word) === 1) {
            return false;
        }

        if ($word == 'ketahui') {
            return false;
        }

        $contains = false
                    || preg_match('/^ber(.*)i$/', $word) === 1
                    || preg_match('/^di(.*)an$/', $word) === 1
                    || preg_match('/^ke(.*)i$/', $word) === 1
                    || preg_match('/^ke(.*)an$/', $word) === 1
                    || preg_match('/^me(.*)an$/', $word) === 1
                    || preg_match('/^ter(.*)an$/', $word) === 1
                    || preg_match('/^per(.*)an$/', $word) === 1;

        return $contains;
    }
}
