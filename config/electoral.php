<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hora de corte para actas atrasadas
    |--------------------------------------------------------------------------
    |
    | Una mesa cuya eleccion ya vencio (fecha pasada) sin acta registrada se
    | considera atrasada de inmediato. Si la eleccion es de hoy, se considera
    | atrasada solo a partir de esta hora (24h, hora del servidor).
    |
    */
    'acta_cutoff_hour' => (int) env('ELECTION_ACTA_CUTOFF_HOUR', 16),

];
