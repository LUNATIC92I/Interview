<?php

/**
 * Reference data: roles, difficulty levels, skills and evaluation rubrics.
 * Rubric weights per criterion sum to 100. "evaluator" names the method used by the rule based engine.
 */
return [
    'roles' => [
        ['candidate', 'Candidat', 'Candidate'],
        ['admin', 'Administrateur', 'Administrator'],
    ],

    'difficulty_levels' => [
        [1, 'introductory', 'Découverte', 'Introductory', 'Questions d\'ouverture et de présentation.', 'Opening and introductory questions.'],
        [2, 'standard', 'Standard', 'Standard', 'Questions courantes d\'un premier entretien.', 'Common first round questions.'],
        [3, 'advanced', 'Avancé', 'Advanced', 'Questions approfondies attendant des exemples précis.', 'In depth questions expecting precise examples.'],
        [4, 'demanding', 'Exigeant', 'Demanding', 'Mises en situation complexes et arbitrages.', 'Complex situations and trade-offs.'],
        [5, 'expert', 'Expert', 'Expert', 'Questions de niveau jury final ou poste de direction.', 'Final panel or executive level questions.'],
    ],

    'skill_categories' => [
        ['behavioral', 'Compétences comportementales', 'Behavioural skills', 1],
        ['leadership', 'Leadership et management', 'Leadership and management', 2],
        ['technical', 'Expertise technique', 'Technical expertise', 3],
        ['business', 'Relation client et business', 'Client and business', 4],
        ['international', 'Coopération internationale', 'International cooperation', 5],
    ],

    // code => [category, fr, en]
    'skills' => [
        'communication' => ['behavioral', 'Communication', 'Communication'],
        'teamwork' => ['behavioral', 'Travail en équipe', 'Teamwork'],
        'adaptability' => ['behavioral', 'Adaptabilité', 'Adaptability'],
        'problem_solving' => ['behavioral', 'Résolution de problèmes', 'Problem solving'],
        'conflict_management' => ['behavioral', 'Gestion des conflits', 'Conflict management'],
        'stress_management' => ['behavioral', 'Gestion de la pression', 'Working under pressure'],
        'initiative' => ['behavioral', 'Esprit d\'initiative', 'Initiative'],
        'organisation' => ['behavioral', 'Organisation et priorités', 'Organisation and priorities'],
        'self_awareness' => ['behavioral', 'Recul et apprentissage', 'Self awareness and learning'],
        'leadership' => ['leadership', 'Leadership', 'Leadership'],
        'decision_making' => ['leadership', 'Prise de décision', 'Decision making'],
        'people_management' => ['leadership', 'Management d\'équipe', 'People management'],
        'strategic_thinking' => ['leadership', 'Vision stratégique', 'Strategic thinking'],
        'change_management' => ['leadership', 'Conduite du changement', 'Change management'],
        'stakeholder_management' => ['leadership', 'Gestion des parties prenantes', 'Stakeholder management'],
        'technical_expertise' => ['technical', 'Maîtrise technique du métier', 'Core technical expertise'],
        'analytical_thinking' => ['technical', 'Esprit d\'analyse', 'Analytical thinking'],
        'security_mindset' => ['technical', 'Culture sécurité', 'Security mindset'],
        'incident_response' => ['technical', 'Réponse aux incidents', 'Incident response'],
        'data_analysis' => ['technical', 'Analyse de données', 'Data analysis'],
        'software_engineering' => ['technical', 'Génie logiciel', 'Software engineering'],
        'systems_networks' => ['technical', 'Systèmes et réseaux', 'Systems and networks'],
        'cloud_devops' => ['technical', 'Cloud et DevOps', 'Cloud and DevOps'],
        'financial_analysis' => ['technical', 'Analyse financière', 'Financial analysis'],
        'risk_compliance' => ['technical', 'Risques et conformité', 'Risk and compliance'],
        'project_methodology' => ['technical', 'Méthodologie de projet', 'Project methodology'],
        'engineering_methods' => ['technical', 'Méthodes d\'ingénierie', 'Engineering methods'],
        'legal_analysis' => ['technical', 'Analyse juridique', 'Legal analysis'],
        'pedagogy' => ['technical', 'Pédagogie', 'Teaching and pedagogy'],
        'operations_logistics' => ['technical', 'Opérations et logistique', 'Operations and logistics'],
        'health_context' => ['technical', 'Environnement des métiers de santé', 'Health sector environment'],
        'customer_orientation' => ['business', 'Orientation client', 'Customer focus'],
        'negotiation' => ['business', 'Négociation', 'Negotiation'],
        'commercial_acumen' => ['business', 'Sens commercial', 'Commercial acumen'],
        'marketing' => ['business', 'Marketing et communication', 'Marketing and communication'],
        'ethics_integrity' => ['international', 'Éthique et intégrité', 'Ethics and integrity'],
        'multicultural' => ['international', 'Coopération multiculturelle', 'Multicultural cooperation'],
        'results_orientation' => ['international', 'Orientation résultats', 'Results orientation'],
        'motivation_fit' => ['international', 'Motivation et adéquation', 'Motivation and fit'],
        'policy_analysis' => ['international', 'Analyse des politiques', 'Policy analysis'],
        'monitoring_evaluation' => ['international', 'Suivi et évaluation', 'Monitoring and evaluation'],
    ],

    // code => [fr, en, desc_fr, desc_en, criteria: [code, evaluator, weight, fr, en, desc_fr, desc_en]]
    'rubrics' => [
        'behavioral' => ['Grille comportementale (STAR)', 'Behavioural grid (STAR)',
            'Évalue une expérience vécue racontée selon la méthode STAR : Situation, Tâche, Actions, Résultats.',
            'Assesses a past experience told with the STAR method: Situation, Task, Actions, Results.', [
            ['relevance', 'relevance', 25, 'Pertinence de la réponse', 'Relevance of the answer', 'La réponse traite la question et couvre les éléments attendus.', 'The answer addresses the question and covers the expected elements.'],
            ['structure', 'structure', 20, 'Clarté et structure', 'Clarity and structure', 'Le récit suit une progression lisible (contexte, tâche, actions, résultat).', 'The story follows a readable progression (context, task, actions, result).'],
            ['actions', 'actions', 25, 'Qualité des actions décrites', 'Quality of the actions described', 'Actions concrètes, personnelles et justifiées.', 'Concrete, personal and justified actions.'],
            ['results', 'results', 20, 'Résultats et preuves concrètes', 'Results and concrete evidence', 'Résultat mesurable ou observable, chiffres lorsque possible.', 'Measurable or observable outcome, figures where possible.'],
            ['reflection', 'reflection', 10, 'Capacité de recul', 'Self reflection', 'Enseignements tirés et ce qui serait fait autrement.', 'Lessons learned and what would be done differently.'],
        ]],
        'technical' => ['Grille technique', 'Technical grid',
            'Évalue l\'exactitude, le raisonnement, la méthode et la qualité de l\'explication.',
            'Assesses accuracy, reasoning, method and quality of explanation.', [
            ['accuracy', 'accuracy', 35, 'Exactitude technique', 'Technical accuracy', 'Notions attendues présentes et correctement mobilisées.', 'Expected concepts present and correctly used.'],
            ['reasoning', 'reasoning', 25, 'Raisonnement', 'Reasoning', 'Justification des choix, hypothèses et compromis.', 'Justified choices, assumptions and trade-offs.'],
            ['method', 'method', 20, 'Méthode de résolution', 'Problem solving method', 'Démarche ordonnée, étapes claires, bonnes pratiques.', 'Ordered approach, clear steps, good practices.'],
            ['communication', 'communication', 20, 'Qualité de la communication', 'Quality of communication', 'Explication claire, longueur adaptée, vocabulaire précis.', 'Clear explanation, suitable length, precise vocabulary.'],
        ]],
        'managerial' => ['Grille managériale', 'Managerial grid',
            'Évalue le leadership, la décision, la communication, la gestion des conflits et des priorités.',
            'Assesses leadership, decision making, communication, conflict and priority management.', [
            ['leadership', 'leadership', 25, 'Leadership', 'Leadership', 'Capacité à mobiliser, déléguer et accompagner une équipe.', 'Ability to engage, delegate and support a team.'],
            ['decision', 'reasoning', 20, 'Prise de décision', 'Decision making', 'Décisions argumentées, arbitrages explicites.', 'Reasoned decisions, explicit trade-offs.'],
            ['communication', 'structure', 20, 'Communication', 'Communication', 'Message structuré, adapté aux interlocuteurs.', 'Structured message adapted to the audience.'],
            ['conflicts_priorities', 'actions', 20, 'Gestion des conflits et des priorités', 'Conflict and priority management', 'Actions concrètes pour traiter tensions et priorités.', 'Concrete actions to handle tensions and priorities.'],
            ['results', 'results', 15, 'Résultats obtenus', 'Results achieved', 'Impact observable sur l\'équipe ou l\'activité.', 'Observable impact on the team or the business.'],
        ]],
        'motivation' => ['Grille motivation', 'Motivation grid',
            'Évalue la sincérité argumentée de la motivation et l\'adéquation avec le poste.',
            'Assesses well argued motivation and fit with the role.', [
            ['motivation', 'motivation_fit', 35, 'Motivation argumentée', 'Argued motivation', 'Raisons précises, liées au poste et à l\'organisation.', 'Precise reasons linked to the role and organisation.'],
            ['relevance', 'relevance', 25, 'Pertinence', 'Relevance', 'Éléments attendus couverts.', 'Expected elements covered.'],
            ['structure', 'structure', 20, 'Structure', 'Structure', 'Réponse organisée et progressive.', 'Organised and progressive answer.'],
            ['communication', 'communication', 20, 'Communication', 'Communication', 'Clarté et concision.', 'Clarity and concision.'],
        ]],
        'international' => ['Grille organisations internationales', 'International organisations grid',
            'Évalue les valeurs, l\'éthique, la coopération multiculturelle et l\'orientation résultats.',
            'Assesses values, ethics, multicultural cooperation and results orientation.', [
            ['values', 'ethics', 25, 'Valeurs et intégrité', 'Values and integrity', 'Intégrité, neutralité, respect de la diversité.', 'Integrity, impartiality, respect for diversity.'],
            ['relevance', 'relevance', 25, 'Pertinence et compétences', 'Relevance and competencies', 'Éléments attendus et compétences visées.', 'Expected elements and targeted competencies.'],
            ['actions', 'actions', 20, 'Actions personnelles', 'Personal actions', 'Contribution personnelle claire.', 'Clear personal contribution.'],
            ['results', 'results', 15, 'Orientation résultats', 'Results orientation', 'Résultats et impact pour les bénéficiaires.', 'Results and impact for beneficiaries.'],
            ['structure', 'structure', 15, 'Structure', 'Structure', 'Réponse structurée, de préférence STAR.', 'Structured answer, ideally STAR.'],
        ]],
        'situational' => ['Grille mise en situation', 'Situational grid',
            'Évalue la démarche face à un cas pratique : analyse, méthode, raisonnement et actions.',
            'Assesses the approach to a practical case: analysis, method, reasoning and actions.', [
            ['relevance', 'relevance', 25, 'Compréhension du cas', 'Understanding of the case', 'Les enjeux du cas sont identifiés.', 'The key issues of the case are identified.'],
            ['method', 'method', 25, 'Démarche', 'Approach', 'Étapes ordonnées et priorisées.', 'Ordered and prioritised steps.'],
            ['reasoning', 'reasoning', 25, 'Raisonnement', 'Reasoning', 'Hypothèses, risques et arbitrages explicités.', 'Assumptions, risks and trade-offs made explicit.'],
            ['actions', 'actions', 15, 'Actions concrètes', 'Concrete actions', 'Actions précises que vous mèneriez.', 'Precise actions you would take.'],
            ['communication', 'communication', 10, 'Clarté', 'Clarity', 'Explication claire et concise.', 'Clear and concise explanation.'],
        ]],
        'negotiation' => ['Grille négociation', 'Negotiation grid',
            'Évalue la préparation, l\'argumentation et la posture de négociation.',
            'Assesses preparation, argumentation and negotiation stance.', [
            ['negotiation', 'negotiation', 35, 'Préparation et arguments', 'Preparation and arguments', 'Référence au marché, à la valeur apportée, au package global.', 'Reference to market, value brought, overall package.'],
            ['reasoning', 'reasoning', 25, 'Argumentation', 'Argumentation', 'Arguments justifiés et ouverture au compromis.', 'Justified arguments and openness to compromise.'],
            ['relevance', 'relevance', 20, 'Pertinence', 'Relevance', 'Éléments attendus couverts.', 'Expected elements covered.'],
            ['communication', 'communication', 20, 'Posture et clarté', 'Stance and clarity', 'Ton professionnel, message clair.', 'Professional tone, clear message.'],
        ]],
        'general' => ['Grille générale', 'General grid',
            'Évalue la pertinence, la structure et la clarté des questions générales d\'entretien.',
            'Assesses relevance, structure and clarity for general interview questions.', [
            ['relevance', 'relevance', 30, 'Pertinence', 'Relevance', 'Éléments attendus couverts.', 'Expected elements covered.'],
            ['structure', 'structure', 25, 'Structure', 'Structure', 'Réponse organisée.', 'Organised answer.'],
            ['communication', 'communication', 20, 'Communication', 'Communication', 'Clarté et longueur adaptée.', 'Clarity and suitable length.'],
            ['motivation', 'motivation_fit', 15, 'Lien avec le poste', 'Link to the role', 'Lien explicite avec le poste visé.', 'Explicit link to the target role.'],
            ['results', 'results', 10, 'Exemples concrets', 'Concrete examples', 'Réalisations ou faits précis.', 'Specific achievements or facts.'],
        ]],
    ],

    // Question type => default rubric
    'type_rubrics' => [
        'hr' => 'general', 'behavioral' => 'behavioral', 'technical' => 'technical', 'motivation' => 'motivation',
        'situational' => 'situational', 'managerial' => 'managerial', 'negotiation' => 'negotiation',
        'international' => 'international', 'problem_solving' => 'situational',
    ],
];
