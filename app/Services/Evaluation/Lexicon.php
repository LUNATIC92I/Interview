<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Bilingual marker lists used by the rule based criteria. Terms are lowercase, without accents,
 * and single words are matched as prefixes; a trailing "=" requires an exact word. These lists describe how an answer is built
 * (structure, actions, evidence), never who the candidate is.
 */
final class Lexicon
{
    private const TERMS = [
        'connectors' => [
            'fr' => ['d\'abord', 'tout d\'abord', 'ensuite', 'puis', 'enfin', 'finalement', 'premierement', 'deuxiemement', 'par ailleurs', 'en parallele', 'en conclusion', 'en resume', 'pour commencer', 'apres', 'de plus', 'cependant', 'toutefois', 'ainsi'],
            'en' => ['first', 'firstly', 'then', 'next', 'finally', 'secondly', 'afterwards', 'in parallel', 'in conclusion', 'to summarise', 'to summarize', 'to start', 'after that', 'moreover', 'however', 'additionally', 'as a result'],
        ],
        'sequence' => [
            'fr' => ['d\'abord', 'ensuite', 'puis', 'enfin', 'etape', 'premiere', 'deuxieme', 'troisieme', 'avant de', 'une fois', 'apres avoir', 'en premier', 'priorit'],
            'en' => ['first', 'then', 'next', 'finally', 'step', 'second', 'third', 'before', 'once', 'after', 'prioriti', 'priority', 'start by'],
        ],
        'actions' => [
            'fr' => ['j\'ai', 'je me suis', 'j\'ai mis', 'j\'ai organise', 'j\'ai propose', 'j\'ai decide', 'j\'ai analyse', 'j\'ai pilote', 'j\'ai conduit', 'j\'ai redige', 'j\'ai cree', 'j\'ai mene', 'j\'ai coordonne', 'j\'ai identifie', 'j\'ai negocie', 'j\'ai forme', 'je verifie', 'je commence', 'je contacte', 'je documente', 'j\'analyse', 'j\'organise', 'je priorise', 'je propose', 'je mets'],
            'en' => ['i led', 'i managed', 'i organised', 'i organized', 'i proposed', 'i decided', 'i analysed', 'i analyzed', 'i built', 'i created', 'i wrote', 'i coordinated', 'i identified', 'i negotiated', 'i trained', 'i implemented', 'i set up', 'i designed', 'i reviewed', 'i check', 'i start', 'i contact', 'i document', 'i analyse', 'i analyze', 'i prioritise', 'i prioritize', 'i would', 'i took'],
        ],
        'personal' => [
            'fr' => ['je=', 'j\'', 'moi-meme', 'personnellement', 'mon role', 'ma responsabilite', 'ma decision'],
            'en' => ['i=', 'myself', 'personally', 'my role', 'my responsibility', 'my decision'],
        ],
        'collective' => [
            'fr' => ['nous=', 'on a', 'l\'equipe a'],
            'en' => ['we=', 'the team did', 'our team'],
        ],
        'results' => [
            'fr' => ['resultat', 'reduit', 'augmente', 'ameliore', 'gagne', 'economise', 'atteint', 'depasse', 'livre', 'obtenu', 'permis de', 'impact', 'diminue', 'resolu', 'succes', 'satisfaction', 'retour positif', 'delai respecte', 'objectif'],
            'en' => ['result', 'reduced', 'increased', 'improved', 'saved', 'achieved', 'exceeded', 'delivered', 'obtained', 'led to', 'impact', 'decreased', 'resolved', 'success', 'satisfaction', 'positive feedback', 'on time', 'target', 'goal', 'outcome'],
        ],
        'reflection' => [
            'fr' => ['j\'ai appris', 'appris', 'lecon', 'avec le recul', 'je referais', 'je ferais differemment', 'aujourd\'hui je', 'retenu', 'retour d\'experience', 'amelioration continue', 'je retiens', 'je changerais', 'erreur'],
            'en' => ['i learned', 'i learnt', 'learned', 'lesson', 'in hindsight', 'looking back', 'differently', 'today i would', 'takeaway', 'lessons learned', 'retrospective', 'continuous improvement', 'i would change', 'mistake'],
        ],
        'reasoning' => [
            'fr' => ['parce que', 'car=', 'donc', 'afin de', 'pour que', 'c\'est pourquoi', 'en effet', 'si=', 'sinon', 'selon', 'en fonction', 'risque', 'compromis', 'avantage', 'inconvenient', 'cela permet', 'consequence', 'hypothese'],
            'en' => ['because', 'therefore', 'so that', 'in order to', 'which is why', 'since', 'if=', 'otherwise', 'depending on', 'risk', 'trade-off', 'tradeoff', 'advantage', 'drawback', 'this allows', 'consequence', 'hypothesis', 'assumption'],
        ],
        'leadership' => [
            'fr' => ['equipe', 'deleg', 'motiv', 'accompagn', 'coach', 'feedback', 'decision', 'arbitr', 'prioris', 'conflit', 'mediation', 'objectifs', 'responsabilis', 'vision', 'confiance', 'ecoute', 'transparence', 'parties prenantes'],
            'en' => ['team', 'delegat', 'motivat', 'coach', 'mentor', 'feedback', 'decision', 'prioriti', 'conflict', 'mediat', 'objectives', 'accountab', 'vision', 'trust', 'listen', 'transparen', 'stakeholder', 'empower'],
        ],
        'ethics' => [
            'fr' => ['integrite', 'ethique', 'transparence', 'confidential', 'neutralite', 'impartial', 'respect', 'diversite', 'inclusion', 'multicultur', 'redevabilite', 'conflit d\'interet', 'deontolog', 'equite', 'mandat', 'valeurs', 'cooperation', 'partenaires'],
            'en' => ['integrity', 'ethic', 'transparen', 'confidential', 'neutral', 'impartial', 'respect', 'diversity', 'inclusion', 'multicultur', 'accountab', 'conflict of interest', 'fairness', 'mandate', 'values', 'cooperation', 'partners'],
        ],
        'motivation' => [
            'fr' => ['motiv', 'passion', 'interesse', 'attire', 'mission', 'valeurs', 'projet', 'contribuer', 'apporter', 'evoluer', 'carriere', 'apprendre', 'sens', 'votre entreprise', 'votre organisation', 'ce poste', 'aligne'],
            'en' => ['motivat', 'passion', 'interested', 'drawn', 'mission', 'values', 'contribute', 'bring', 'grow', 'career', 'learn', 'purpose', 'your company', 'your organisation', 'your organization', 'this role', 'this position', 'align'],
        ],
        'negotiation' => [
            'fr' => ['marche', 'fourchette', 'etude', 'benchmark', 'remuneration', 'package', 'avantages', 'variable', 'flexib', 'valeur', 'experience', 'responsabilites', 'teletravail', 'formation', 'evolution', 'contre-proposition', 'brut', 'net'],
            'en' => ['market', 'range', 'research', 'benchmark', 'compensation', 'package', 'benefits', 'bonus', 'flexib', 'value', 'experience', 'responsibilities', 'remote', 'training', 'growth', 'counter', 'gross', 'base salary'],
        ],
        'star' => [
            'fr' => ['situation', 'contexte', 'tache', 'mission', 'objectif', 'action', 'resultat', 'j\'ai'],
            'en' => ['situation', 'context', 'task', 'objective', 'goal', 'action', 'result', 'i='],
        ],
        'filler' => [
            'fr' => ['euh', 'bref', 'du coup', 'genre', 'en fait en fait', 'voila voila', 'blabla', 'etc etc'],
            'en' => ['umm', 'uh', 'like like', 'you know', 'whatever', 'blah', 'etc etc', 'stuff'],
        ],
    ];

    /** @return list<string> */
    public static function get(string $group, string $locale): array
    {
        return self::TERMS[$group][$locale] ?? self::TERMS[$group]['en'] ?? [];
    }
}
