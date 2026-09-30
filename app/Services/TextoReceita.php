<?php
// app/Services/TextoReceita.php
// A base da Receita Federal traz nomes e endereços em MAIÚSCULAS e sem acento
// (ex: "TUPAN CONSTRUCOES LTDA"). Esta classe converte pra capitalização normal e
// recoloca os acentos mais comuns: "Tupan Construções LTDA".
//
// Acentos voltam por dois caminhos: terminações sempre acentuadas (-ção, -ções,
// -ência, -ância, -ssão...) e um dicionário de palavras frequentes em nomes de
// empresas, logradouros e cidades do Nordeste. O que não estiver em nenhum dos dois
// fica sem acento — dá pra corrigir à mão antes de salvar.
class TextoReceita {
    // Ficam sempre em maiúsculas (formas jurídicas e siglas comuns).
    private const SIGLAS = [
        'LTDA', 'ME', 'EPP', 'EIRELI', 'SPE', 'MEI', 'SS', 'SLU', 'CNPJ',
        'BR', 'PE', 'PB', 'RN', 'AL', 'CE', 'BA', 'SE', 'PI', 'MA',
        'TV', 'FM', 'AM', 'ONG', 'APAE', 'SESC', 'SENAC', 'SESI', 'SENAI', 'SEBRAE',
        'UFPE', 'UFPB', 'UFRN', 'UFAL', 'IFPE', 'CPF', 'PDV', 'RH', 'TI',
    ];

    // Ficam em minúsculas no meio do nome.
    private const CONECTIVOS = [
        'DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'EM', 'NA', 'NO', 'NAS', 'NOS',
        'A', 'O', 'AS', 'OS', 'COM', 'PARA', 'POR', 'PELA', 'PELO', 'AO', 'AOS', 'À', 'ÀS',
    ];

    // Palavras cujo acento não dá pra deduzir pela terminação.
    private const ACENTOS = [
        // Negócios / ramos
        'COMERCIO' => 'comércio', 'SERVICOS' => 'serviços', 'SERVICO' => 'serviço',
        'INDUSTRIA' => 'indústria', 'INDUSTRIAS' => 'indústrias', 'INDUSTRIAL' => 'industrial',
        'MIDIA' => 'mídia', 'MIDIAS' => 'mídias', 'GRAFICA' => 'gráfica', 'GRAFICOS' => 'gráficos',
        'INFORMATICA' => 'informática', 'ELETRICA' => 'elétrica', 'ELETRICOS' => 'elétricos',
        'ELETRICO' => 'elétrico', 'ELETRONICA' => 'eletrônica', 'ELETRONICOS' => 'eletrônicos',
        'ELETRODOMESTICOS' => 'eletrodomésticos', 'HIDRAULICA' => 'hidráulica',
        'TECNICA' => 'técnica', 'TECNICOS' => 'técnicos', 'TECNICO' => 'técnico',
        'LOGISTICA' => 'logística', 'MECANICA' => 'mecânica', 'QUIMICA' => 'química',
        'CLINICA' => 'clínica', 'CLINICAS' => 'clínicas', 'MEDICA' => 'médica', 'MEDICO' => 'médico',
        'MEDICOS' => 'médicos', 'ODONTOLOGICA' => 'odontológica', 'LABORATORIO' => 'laboratório',
        'FARMACIA' => 'farmácia', 'FARMACIAS' => 'farmácias', 'DROGARIA' => 'drogaria',
        'OTICA' => 'ótica', 'OPTICA' => 'óptica', 'SAUDE' => 'saúde', 'ODONTO' => 'odonto',
        'VEICULOS' => 'veículos', 'VEICULO' => 'veículo', 'AUTOMOVEIS' => 'automóveis',
        'PECAS' => 'peças', 'PRECO' => 'preço', 'PRECOS' => 'preços', 'IMOVEIS' => 'imóveis', 'IMOBILIARIA' => 'imobiliária',
        'IMOBILIARIO' => 'imobiliário', 'MOVEIS' => 'móveis', 'CALCADOS' => 'calçados',
        'CONFECCOES' => 'confecções', 'ACUCAR' => 'açúcar', 'ALCOOL' => 'álcool', 'AGUA' => 'água',
        'AGUAS' => 'águas', 'PAO' => 'pão', 'PAES' => 'pães', 'CAFE' => 'café', 'CARNES' => 'carnes',
        'NEGOCIOS' => 'negócios', 'CONTABIL' => 'contábil', 'CONTABEIS' => 'contábeis',
        'PUBLICA' => 'pública', 'PUBLICO' => 'público', 'PUBLICOS' => 'públicos',
        'MUNICIPIO' => 'município', 'MUNICIPAL' => 'municipal', 'CAMARA' => 'câmara',
        'SECRETARIA' => 'secretaria', 'ACADEMIA' => 'academia', 'ESTETICA' => 'estética',
        'ENERGIA' => 'energia', 'SOLAR' => 'solar', 'OLEO' => 'óleo', 'OLEOS' => 'óleos',
        'PETROLEO' => 'petróleo', 'COMBUSTIVEIS' => 'combustíveis', 'POSTO' => 'posto',
        'AGENCIA' => 'agência', 'PROPAGANDA' => 'propaganda', 'PUBLICIDADE' => 'publicidade',
        'MARKETING' => 'marketing', 'EDITORA' => 'editora', 'ESCRITORIO' => 'escritório',
        'CIENCIAS' => 'ciências', 'MAQUINAS' => 'máquinas', 'MAQUINA' => 'máquina',
        'MATERIAIS' => 'materiais', 'CERAMICA' => 'cerâmica', 'CERAMICAS' => 'cerâmicas',
        'VIDROS' => 'vidros', 'ACO' => 'aço', 'ACOS' => 'aços', 'METALURGICA' => 'metalúrgica',
        'PLASTICOS' => 'plásticos', 'PLASTICO' => 'plástico', 'TEXTIL' => 'têxtil',
        'ALIMENTICIOS' => 'alimentícios', 'LATICINIOS' => 'laticínios', 'GENEROS' => 'gêneros',
        'HORTIFRUTI' => 'hortifrúti', 'AGRICOLA' => 'agrícola', 'AGROPECUARIA' => 'agropecuária',
        'PECUARIA' => 'pecuária', 'ACOUGUE' => 'açougue', 'SUPERMERCADO' => 'supermercado',
        'ATACADAO' => 'atacadão', 'MERCADAO' => 'mercadão', 'BARATAO' => 'baratão',
        'CIA' => 'cia', 'ASSOCIACAO' => 'associação', 'UNIAO' => 'união', 'IRMAOS' => 'irmãos',
        'IRMAO' => 'irmão', 'FILHOS' => 'filhos', 'PATRIMONIO' => 'patrimônio',
        'ECONOMICA' => 'econômica', 'CREDITO' => 'crédito', 'SEGUROS' => 'seguros',
        'EDUCACIONAL' => 'educacional', 'COLEGIO' => 'colégio', 'CENTRO' => 'centro',
        'TURISMO' => 'turismo', 'HOTEIS' => 'hotéis', 'HOTEL' => 'hotel', 'POUSADA' => 'pousada',
        'GAS' => 'gás', 'DIGITAL' => 'digital', 'ACESSORIOS' => 'acessórios',
        'ESPORTES' => 'esportes', 'OPTICAS' => 'ópticas', 'OTICAS' => 'óticas',
        'NUTRICAO' => 'nutrição', 'ANIMAL' => 'animal', 'VETERINARIA' => 'veterinária',
        'TRANSITO' => 'trânsito', 'ONIBUS' => 'ônibus', 'AEREO' => 'aéreo', 'AEREA' => 'aérea',
        'MAO' => 'mão', 'OBRA' => 'obra', 'SOLUCOES' => 'soluções', 'NUMERO' => 'número',
        'UNICA' => 'única', 'UNICO' => 'único', 'BASICA' => 'básica', 'BRASIL' => 'brasil',
        'BRASILEIRA' => 'brasileira', 'NACIONAL' => 'nacional', 'REGIAO' => 'região',
        'SERTAO' => 'sertão', 'AGRESTE' => 'agreste', 'NORDESTE' => 'nordeste',
        'NOBREGA' => 'nóbrega', 'GALVAO' => 'galvão', 'ARAUJO' => 'araújo', 'ASSUNCAO' => 'assunção',
        'SIMOES' => 'simões', 'GUIMARAES' => 'guimarães', 'MAGALHAES' => 'magalhães',
        'LEAO' => 'leão', 'BRANDAO' => 'brandão', 'FALCAO' => 'falcão', 'ROMAO' => 'romão',
        'GONCALVES' => 'gonçalves', 'CONCEICAO' => 'conceição', 'LUCIA' => 'lúcia',
        'ANTONIO' => 'antônio', 'ANTONIA' => 'antônia', 'FATIMA' => 'fátima', 'JOAO' => 'joão',
        'JOSE' => 'josé', 'SEBASTIAO' => 'sebastião', 'ANDRE' => 'andré', 'CESAR' => 'césar',
        'MARCIO' => 'márcio', 'MARCIA' => 'márcia', 'FABIO' => 'fábio', 'FLAVIO' => 'flávio',
        'SERGIO' => 'sérgio', 'VINICIUS' => 'vinícius', 'VITOR' => 'vítor', 'MONICA' => 'mônica',
        'PATRICIA' => 'patrícia', 'CLAUDIO' => 'cláudio', 'CLAUDIA' => 'cláudia', 'ROMULO' => 'rômulo',
        'TULIO' => 'túlio', 'EMILIA' => 'emília', 'VALERIA' => 'valéria', 'VERONICA' => 'verônica',
        'JESUS' => 'jesus', 'GLORIA' => 'glória', 'VITORIA' => 'vitória', 'MARIO' => 'mário',
        'SIMAO' => 'simão', 'ESTEVAO' => 'estevão', 'ROGERIO' => 'rogério',

        // Endereço
        'PRACA' => 'praça', 'RODOVIA' => 'rodovia', 'GALPAO' => 'galpão', 'TERREO' => 'térreo',
        'EDIFICIO' => 'edifício', 'CONDOMINIO' => 'condomínio', 'PAVIMENTO' => 'pavimento',
        'LOTEAMENTO' => 'loteamento', 'ACESSO' => 'acesso', 'PATIO' => 'pátio', 'ARMAZEM' => 'armazém',
        'SITIO' => 'sítio', 'FAZENDA' => 'fazenda', 'POVOADO' => 'povoado', 'DISTRITO' => 'distrito',
        'AREA' => 'área', 'PREDIO' => 'prédio', 'MODULO' => 'módulo', 'NUCLEO' => 'núcleo',
        'JARDIM' => 'jardim', 'VARZEA' => 'várzea', 'GRACAS' => 'graças', 'BOA' => 'boa',
        'IMBIRIBEIRA' => 'imbiribeira', 'JIQUIA' => 'jiquiá', 'TORROES' => 'torrões',
        'MAURITSSTAD' => 'mauritsstad', 'PINA' => 'pina', 'CAXANGA' => 'caxangá',
        'AGAMENON' => 'agamenon', 'CONSELHEIRO' => 'conselheiro', 'MARECHAL' => 'marechal',
        'GETULIO' => 'getúlio', 'EPITACIO' => 'epitácio', 'PESSOA' => 'pessoa', 'MAURICIO' => 'maurício',
        'CAMPOS' => 'campos', 'TANCREDO' => 'tancredo', 'IPIRANGA' => 'ipiranga',
        'ESTACAO' => 'estação', 'ESTADOS' => 'estados', 'REPUBLICA' => 'república',
        'INDEPENDENCIA' => 'independência', 'LIBERDADE' => 'liberdade', 'MARTIRES' => 'mártires',

        // Cidades / estados
        'SAO' => 'são', 'JABOATAO' => 'jaboatão', 'GRAVATA' => 'gravatá', 'ANTAO' => 'antão',
        'CAMARAGIBE' => 'camaragibe', 'IGARASSU' => 'igarassu', 'GOIANA' => 'goiana',
        'TAMANDARE' => 'tamandaré', 'SERINHAEM' => 'serinhaém', 'IPOJUCA' => 'ipojuca',
        'CABO' => 'cabo', 'AGOSTINHO' => 'agostinho', 'CARPINA' => 'carpina', 'LIMOEIRO' => 'limoeiro',
        'SURUBIM' => 'surubim', 'SANTA' => 'santa', 'CAPIBARIBE' => 'capibaribe', 'TORITAMA' => 'toritama',
        'SAIRE' => 'sairé', 'CARUARU' => 'caruaru', 'BEZERROS' => 'bezerros', 'PESQUEIRA' => 'pesqueira',
        'ARCOVERDE' => 'arcoverde', 'BUIQUE' => 'buíque', 'CUSTODIA' => 'custódia',
        'PETROLANDIA' => 'petrolândia', 'SALGUEIRO' => 'salgueiro', 'OURICURI' => 'ouricuri',
        'ARARIPINA' => 'araripina', 'AFOGADOS' => 'afogados', 'INGAZEIRA' => 'ingazeira',
        'TABIRA' => 'tabira', 'GARANHUNS' => 'garanhuns', 'BELEM' => 'belém', 'MACEIO' => 'maceió',
        'MOSSORO' => 'mossoró', 'PARAIBA' => 'paraíba', 'CEARA' => 'ceará', 'PIAUI' => 'piauí',
        'MARANHAO' => 'maranhão', 'AMAPA' => 'amapá', 'GOIAS' => 'goiás',
        'GOIANIA' => 'goiânia', 'BRASILIA' => 'brasília', 'PARANA' => 'paraná', 'RONDONIA' => 'rondônia',
        'ESPIRITO' => 'espírito', 'LUIS' => 'luís', 'TERESINA' => 'teresina',
        'PATOS' => 'patos', 'SOUSA' => 'sousa', 'CAJAZEIRAS' => 'cajazeiras', 'GUARABIRA' => 'guarabira',
        'CAICO' => 'caicó', 'PARNAMIRIM' => 'parnamirim', 'ARAPIRACA' => 'arapiraca',
        'PENEDO' => 'penedo', 'PALMEIRA' => 'palmeira', 'INDIOS' => 'índios', 'CANINDE' => 'canindé',
        'SAMPAIO' => 'sampaio', 'CAMPINA' => 'campina', 'JUAZEIRO' => 'juazeiro', 'CRATO' => 'crato',
        'PETROLINA' => 'petrolina', 'SERRA' => 'serra', 'TALHADA' => 'talhada', 'TRES' => 'três',
        'CORACOES' => 'corações', 'VICOSA' => 'viçosa',
        'IMPERIO' => 'império', 'CRITERIO' => 'critério', 'MINISTERIO' => 'ministério',
        'SANATORIO' => 'sanatório', 'CONSULTORIO' => 'consultório', 'DEPOSITO' => 'depósito',
        'EXPRESSO' => 'expresso', 'SAOS' => 'sãos',
    ];

    public static function formatar(?string $texto): string {
        $texto = trim(preg_replace('/\s+/u', ' ', (string)$texto));
        if ($texto === '') return '';

        // Separa palavras dos separadores (espaço, pontuação), mantendo os separadores.
        $partes = preg_split('/([^\p{L}\p{N}]+)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $palavras = array_keys(array_filter($partes, fn($p) => preg_match('/[\p{L}\p{N}]/u', $p)));
        $ultima = end($palavras);
        $primeira = true;
        foreach ($palavras as $i) {
            $p = $partes[$i];
            // "SA" no fim do nome é sociedade anônima; no meio, o sobrenome "Sá".
            if (mb_strtoupper($p, 'UTF-8') === 'SA') {
                $partes[$i] = $i === $ultima ? 'SA' : 'Sá';
                $primeira = false;
                continue;
            }
            $partes[$i] = self::palavra($p, $primeira);
            $primeira = false;
        }
        $saida = implode('', $partes);

        // "S/A", "S.A.", "S/N" (sem número) e "Cia." têm forma fixa.
        $saida = preg_replace('/\bS\s*\/\s*a\b/u', 'S/A', $saida);
        $saida = preg_replace('/\bS\.\s*a\b/u', 'S.A', $saida);
        return $saida;
    }

    private static function palavra(string $p, bool $primeira): string {
        $up = mb_strtoupper($p, 'UTF-8');

        if (in_array($up, self::SIGLAS, true)) return $up;
        if (preg_match('/\d/', $up)) return $up;                     // "BR232", "KM", números
        if (preg_match('/^[IVX]{2,4}$/', $up)) return $up;           // numeral romano (II, III, IV)
        if (!preg_match('/[AEIOUYÁÉÍÓÚÂÊÔÃÕÀ]/u', $up)) return $up;   // sem vogal = sigla (MRV, JBS)
        if (!$primeira && in_array($up, self::CONECTIVOS, true)) return mb_strtolower($up, 'UTF-8');

        $baixa = self::ACENTOS[$up] ?? self::acentoPorTerminacao(mb_strtolower($up, 'UTF-8'));
        return mb_strtoupper(mb_substr($baixa, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($baixa, 1, null, 'UTF-8');
    }

    // Terminações que em português são (quase) sempre acentuadas.
    // Em português quase não existe palavra terminada em "ao"/"oes"/"aes" sem til
    // (Aragão, Simões, Guimarães, Irmãos).
    private static function acentoPorTerminacao(string $w): string {
        if (mb_strlen($w, 'UTF-8') < 3) return $w;
        $regras = [
            '/cao$/' => 'ção', '/coes$/' => 'ções',
            '/ao$/' => 'ão', '/aos$/' => 'ãos', '/oes$/' => 'ões', '/aes$/' => 'ães',
            '/encia$/' => 'ência', '/encias$/' => 'ências',
            '/ancia$/' => 'ância', '/ancias$/' => 'âncias',
            '/avel$/' => 'ável', '/ivel$/' => 'ível', '/aveis$/' => 'áveis', '/iveis$/' => 'íveis',
        ];
        foreach ($regras as $de => $para) {
            $novo = preg_replace($de, $para, $w, 1, $n);
            if ($n) return $novo;
        }
        return $w;
    }
}
