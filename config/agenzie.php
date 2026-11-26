<?php

// Personalizzazioni chieste da una sola agenzia, per id tenant.
return [
    // Infortunistica Milano (Carlo La Vecchia)
    11 => [
        // Pacchetto PDF: seconda riga con il liquidatore del sinistro (telefono, email).
        'pdf_liquidatore' => true,
        // Pacchetto PDF: riga "Data del sinistro" in Dati Principali, presa da questo campo data
        // del sinistro (Impostazioni → Campi). Se non c'è, il primo campo data con "sinistro" nel nome.
        'pdf_data_sinistro' => 'data_accadimento_sinistro',
        // Categorie proposte in rubrica, oltre a quelle di tutti.
        'categorie_rubrica' => ['medico legale di parte', 'medico legale assicurazione'],
    ],
];
