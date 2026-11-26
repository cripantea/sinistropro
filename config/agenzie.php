<?php

// Personalizzazioni chieste da una sola agenzia, per id tenant.
return [
    // Infortunistica Milano (Carlo La Vecchia)
    11 => [
        // Pacchetto PDF: seconda riga con il liquidatore del sinistro (telefono, email).
        'pdf_liquidatore' => true,
        // Categorie proposte in rubrica, oltre a quelle di tutti.
        'categorie_rubrica' => ['medico legale di parte', 'medico legale assicurazione'],
    ],
];
