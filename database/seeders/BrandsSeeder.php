<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandModel;
use App\Services\LangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandsSeeder extends Seeder
{
    private array $brandsData = [
        'HONGQI' => ['E-HS9', 'E-QM5', 'H5', 'HS5'],
        'CHANGAN' => [
            'Alsvin', 'Alsvin V7', 'Benni', 'Benni E-Star', 'CS 15', 'CS 35', 'CS 35 PLUS',
            'CS 55 PLUS', 'CS 75', 'CS 75 PLUS', 'CS 85', 'CS 95', 'CX 20', 'DEEPAL G318',
            'DEEPAL S05', 'DEEPAL S07', 'DEEPAL S09', 'DEEPAL SL03', 'Eado', 'Eado Plus',
            'Eado XT', 'F70(Hunter)', 'Honor', 'K50(Hunter)', 'Lamore', 'Nevo A05', 'Nevo Q05',
            'Nevo Q07', 'Oshan X5 Plus', 'Oshan Z6', 'Peak View RV', 'Qiyuan A05', 'Qiyuan A06',
            'Qiyuan A06E', 'Qiyuan A07', 'Qiyuan Q05', 'Qiyuan Q07', 'Star', 'Uni-K',
            'Uni-K iDD', 'Uni T', 'Uni V', 'Uni V iDD', 'Uni Z',
        ],
        'AUDI' => [
            '100', '200', '80', 'A1', 'A3', 'A4', 'A5', 'A6', 'A6 allroad', 'A7', 'A8',
            'E5', 'Q3', 'Q5', 'Q7', 'Q8', 'Q8 Sportback e-tron', 'RS Q8', 'RS4', 'RS6',
            'RS7', 'S5', 'S7', 'S8', 'TT', 'e-tron',
        ],
        'Li Auto' => ['L6', 'L7', 'L9', 'Mega', 'I8'],
        'JAC' => [
            'Ankai', 'HFC1020', 'HFC1045KRD', 'HFC1061K5P', 'HFC5061', 'J7', 'JS2', 'JS3',
            'JS4', 'JS6', 'JS8', 'LD1A2', 'LE79Z', 'M5', 'RF8', 'S3', 'S3 Pro', 'S8',
            'Sunray', 'T6', 'T8', 'T8 Pro', 'T9',
        ],
        'Geely' => [
            'Atlas Pro', 'CK', 'Coolray', 'Emgrand', 'Emgrand EC7', 'EMgrand L', 'Emgrand X7',
            'Galaxy A7', 'Galaxy L6', 'Galaxy L7', 'Galaxy M9', 'Galaxy Starshine 6',
            'Galaxy Starship 7', 'Geometry E', 'Geometry Panda mini', 'MK', 'Monjaro',
            'Okavango', 'Okavango L', 'Radar RD6', 'TX4', 'Tugella S',
        ],
        'Ford' => [
            'Bronco', 'Bronco Sport', 'C-MAX', 'Cargo', 'Contour', 'Courier', 'Ecosport',
            'Edge', 'Escape', 'Escort', 'Expedition', 'Explorer', 'F-150', 'F-450', 'Fiesta',
            'Flex', 'Focus', 'Fusion', 'Fusion (North America)', 'Galaxie', 'Galaxy', 'Granada',
            'Kuga', 'Maverick', 'Mondeo', 'Mustang', 'Mustang match-E', 'Ranger', 'S-MAX',
            'Scorpio', 'Tourneo Connect', 'Tourneo Courier', 'Tourneo Custom', 'Transit',
            'Transit Connect', 'Transit Custom',
        ],
        'Wolkswagen' => [
            'Amarok', 'Arteon', 'Atlas', 'Beetle', 'Bora', 'Caddy', 'Caravelle', 'Crafter',
            'Golf', 'Golf Plus', 'Golf R', 'ID.4', 'ID.4 Crozz', 'ID.4X', 'ID.6', 'ID.6 CROZZ',
            'ID.6 X', 'ID.7', 'ID.UNYX', 'Jetta', 'Magotan', 'Multivan', 'Parati', 'Passat',
            'Passat (North America)', 'Passat CC', 'Phaeton', 'Pointer', 'Polo', 'Scirocco',
            'Scirocco R', 'Sharan', 'Taos', 'Tayron', 'Teramont', 'Tiguan', 'Touareg',
            'Touran', 'Transporter', 'Vento',
        ],
        'NISSAN' => [
            'Almera', 'Altima', 'Ariya', 'Armada', 'Bluebird Sylphy', 'Juke', 'Kicks', 'Latio',
            'Leaf', 'Magnite', 'March', 'Maxima', 'Micra', 'Murano', 'Navara', 'Note', 'Paladin',
            'Pathfinder', 'Patrol', 'Primera', 'Qashqai', 'Rogue', 'Sentra', 'Serena', 'Skyline',
            'Sunny', 'Sylphy', 'Teana', 'Terrano', 'Terrano II', 'Tiida', 'Urvan', 'Versa',
            'Wingroad', 'X-Trail', 'Xterra', 'Z Nismo',
        ],
        'ZX AUTO' => ['TERRALORD'],
        'TOYOTO' => [
            '4Runner', 'Alphard', 'Aqua', 'Auris', 'Avalon', 'Avanza', 'Avensis', 'C-HR',
            'Camry', 'Corollo', 'Corolla Axio', 'Corolla Cross', 'Corolla Fielder',
            'Corolla Levin', 'Corolla Verso', 'Corolla Im', 'Crown', 'Esquire', 'FJ Cruiser',
            'Fortuner', 'Frontlander', 'GT 86', 'Grand Highlander', 'Harrier', 'HiACE',
            'Highlander', 'Hilux', 'Hilux Surf', 'Land Cruiser', 'Land Cruiser Prado', 'Matrix',
            'Passo', 'Previa', 'Prius', 'Prius C', 'Prius PHV', 'Prius V', 'Proace City',
            'RAV4', 'Rush', 'Squoia', 'Sienna', 'Sienta', 'Solara', 'Supra', 'Tundra', 'Venza',
            'Verso-S', 'Vitz', 'Yaris', 'Yaris Cross', 'Yaris iA', 'Bb', 'bZ3', 'Bz4x',
        ],
        'BYD' => [
            'Destroyer 05', 'F0', 'F3', 'F6', 'Frigate 07', 'G3', 'Han', 'Han L', 'Leopard 3',
            'Leopard 5', 'Leopard 7', 'Leopard 8', 'Qin L', 'Qin Plus', 'Sea Lion 05',
            'Sea Lion 06', 'Seagull', 'Seal 05', 'Seal 06', 'Song L', 'Song Plus M-I',
            'Song Plus EV', 'Song Pro', 'T5', 'Tang L', 'YangWang U8L', 'YangWang U8',
            'YangWang U9', 'Yuan Plus', 'Yuan Up', 'E2',
        ],
        'ZEEKR' => ['001', '007', '007 GT', '009', '7X', '9X', 'X'],
        'Jetour' => [
            'Dashing', 'T1', 'T2', 'T2 i-DM', 'X70', 'X70 PLUS', 'X70C-DM', 'X70S',
            'X90 PLUS', 'X95',
        ],
        'AVATR' => ['06', '07', '11', '12'],
        'Mercedes' => [
            '170 V', '190', '200', '220(W187)', '300', 'A CLASS', 'A 140', 'A 160', 'A 170',
            'A 180', 'A 190', 'A 200', 'A 220', 'A 250', 'AMG GT', 'AMG GT 43',
            'AMG GT 43 4MATIC+', 'AMG GT 53 4MATIC+', 'AMG GT 63 4MATIC+',
            'AMG GT 63 S 4MATIC+', 'Actros', 'Actros 1840', 'Actros 1841', 'Actros 1842',
            'Actros 1843', 'Actros 1844', 'Actros 1846', 'Actros 1848', 'Actros 2540',
            'Actros 2544', 'Actros 2546', 'Actros 3235', 'Atego 1218', 'Atego 1222',
            'Atego 1223', 'Atego 1224', 'Atego 1228', 'Atego 1229', 'Atego 1317', 'Atego 1323',
            'Atego 1324', 'Atego 1328', 'Atego 1518', 'Atego 1523', 'Atego 1524', 'Atego 1529',
            'Atego 1823', 'Atego 1828', 'Atego 2528', 'Atego 809', 'Atego 814', 'Atego 815',
            'Atego 816', 'Atego 817', 'Atego 818', 'Atego 917', 'Axor 1824', 'Axor 1829',
            'Axor 1833', 'Axor 1835', 'Axor 1836', 'Axor 1840', 'Axor 1843', 'Axor 2529',
            'Axor 2533', 'Axor 3340', 'B-CLASS', 'B 170', 'B 180', 'B 200', 'C-Class', 'C 180',
            'C 200', 'C 200 4MATIC', 'C 200 d', 'C 220', 'C 220 d', 'C 230', 'C 240',
            'C 240 4MATIC', 'C 250', 'C 270', 'C 280', 'C 280 4MATIC', 'C 300', 'C 300 4MATIC',
            'C 300 e', 'C 320', 'C 350 4MATIC', 'C 350 e', 'C-Class AMG', 'C 43 AMG 4MATIC',
            'C 63 AMG', 'C 63 AMG 4MATIC', 'CL-Class', 'CL 500', 'CL-Class AMG', 'CL 65 AMG',
            'CLA-Class', 'CLA 200', 'CLA 220', 'CLA 250', 'CLA 250 4MATIC', 'CLA 250 e',
            'CLA-Class AMG', 'CLA 35 AMG 4MATIC', 'CLA 45 AMG 4MATIC', 'CLE-CLASS', 'CLE 200',
            'CLE 300', 'CLE-CLASS AMG', 'CLE 53 AMG 4MATIC+', 'CLK-Class', 'CLK 200',
            'CLK 230', 'CLK 320', 'CLS-Class', 'CLS 250 4MATIC', 'CLS 250 CDI', 'CLS 300',
            'CLS 320', 'CLS 350', 'CLS 450 4MATIC', 'CLS 500', 'CLS-CLASS AMG',
            'CLS 53 AMG 4MATIC+', 'CLS 55 AMG', 'CLS 63 AMG', 'CITAN', 'Commercial', '0303',
            '0403', '1217', '1222', '208 D', '307 D', '308 D', '408 D', '410 D', '412 D',
            '508', '609 D', '709 D', '811 D', '813', '814 D', 'Vario', 'E-Class', '200 D',
            '200 E', '220 E', '230 E', '250 D', '260 E', '260 E 4MATIC', '280 E', '300 D',
            '300 E', 'E 200', 'E 200 4MATIC', 'E 220 D', 'E 220 D 4matic', 'E 230', 'E 240',
            'E 240 4MATIC', 'E 250', 'E 250 4MATIC', 'E 270', 'E 280', 'E 280 4MATIC', 'E 290',
            'E 300', 'E 300 4MATIC', 'E 300 D', 'E 300 De', 'E 300 e', 'E 320 4MATIC', 'E 350',
            'E 350 4MATIC', 'E 350 e', 'E 400', 'E 400 e 4MATIC', 'E 420', 'E 430',
            'E 450 4MATIC', 'E 500', 'E-Class AMG', 'E 43 AMG 4MATIC', 'EQA', 'EQA 250',
            'EQA 260', 'EQB', 'EQB 250', 'EQB 260', 'EQB 300 4MATIC', 'EQC', 'Eqc 400 4matic',
            'EQE', 'EQE 300', 'EQE 350', 'EQE 350+', 'EQE AMG', 'AMG EQE 53 4MATIC',
            'EQE 43 AMG', 'EQE SUV', 'EQE 350+ SUV', 'EQE SUV', 'EQS', 'EQS 450',
            'EQS 450 4MATIC', 'EQS 450 4MATIC SUV', 'EQS 450+', 'EQS 500 4MATIC',
            'EQS 580 4MATIC', 'EQS 580 4MATIC SUV', 'EQS 53 AMG 4MATIC+', 'EQV 300', 'G 300',
            'G 350', 'G 400', 'G 450', 'G 500', 'G 55 AMG', 'G 63 AMG', 'GL 320 CDI 4MATIC',
            'GL 350 4MATIC', 'GL 420 CDI 4MATIC', 'GL 450 4MATIC', 'GL 500 4MATIC', 'GL 550',
            'GL 550 4MATIC', 'GL 63 AMG', 'GL 63 AMG 4MATIC', 'GLA 200', 'GLA 220', 'GLA 250',
            'GLA 250 4MATIC', 'GLA 45 AMG 4MATIC', 'GLB 200 4MATIC', 'GLB 220', 'GLB 250',
            'GLB 250 4MATIC', 'GLB 35 AMG 4MATIC', 'GLC 200 4MATIC', 'GLC 250 4MATIC',
            'GLC 300', 'GLC 300 4MATIC', 'GLC 300 e 4MATIC', 'GLC 350 e 4MATIC',
            'GLC 400 e 4MATIC', 'GLC 200 4MATIC Coupe', 'GLC 250 4MATIC Coupe',
            'GLC 300 4MATIC Coupe', 'GLC 300 e 4MATIC Coupe', 'GLC 350 e 4MATIC Coupe',
            'GLE 250', 'GLE 250 4MATIC', 'GLE 350', 'GLE 350 4MATIC', 'GLE 350 de 4MATIC',
            'GLE 350 e 4MATIC', 'GLE 400 4MATIC', 'GLE 450 4MATIC', 'GLE 450 e 4MATIC',
            'GLE 53 AMG 4MATIC+', 'GLE 350 4MATIC Coupe', 'GLE 350 de 4MATIC Coupe',
            'GLE 350 e 4MATIC Coupe', 'GLE 400 4MATIC Coupe', 'GLE 400 e 4MATIC Coupe',
            'GLE 43 AMG 4MATIC Coupe', 'GLE 53 AMG 4MATIC+ Coupe', 'GLE 63 S AMG 4MATIC Coupe',
            'GLK 220 4MATIC', 'GLK 250 4MATIC', 'GLK 300 4MATIC', 'GLK 320 4MATIC', 'GLK 350',
            'GLK 350 4MATIC',
        ],
        'BAIC' => [
            'A115', 'BJ30', 'D20', 'M20', 'M60', 'Senova D50', 'Senova X55', 'X25', 'X3',
            'X35', 'X55', 'X55 II', 'X7',
        ],
        'CHEVROLET' => [
            'Aveo', 'Bolt', 'Camaro', 'Captiva', 'Cobalt', 'Corvette', 'Cruze', 'Cruze (HR)',
            'Damas', 'Epica', 'Equinox', 'HHR', 'Kalos', 'Labo', 'Lacetti', 'Lumina', 'Malibu',
            'Matiz', 'Monza', 'Nexia', 'Niva', 'Onix', 'Orlando', 'Rezzo', 'Silverado', 'Sonic',
            'Spark', 'Suburban', 'Tahoe', 'Tracker', 'TrailBlazer', 'Trax', 'Volt',
        ],
        'Mazda' => [
            '2', '3', '323', '5', '6', '626', 'CX-5', 'CX-60', 'CX-7', 'CX-9', 'CX-90',
            'EZ-60', 'MX-30', 'MX-5', 'T3500', 'Titan', 'Tribute',
        ],
        'Bestune' => ['B70', 'T33', 'T55', 'T77', 'T99'],
        'BMW' => [
            '118', '120', '2 series', '228', '230', '3 series', '316', '318', '320e', '323',
            '325', '328', '330', '330e', '335', '340', '3200', '4 series', '420', '428', '430',
            '5 series', '518', '520', '520 GT', '523', '523d', '525', '528', '530', '530e',
            '535', '540', '545', '545e', '550', '6 series', '640', '650', '7 series', '728',
            '730', '735', '740', '740Le', '750', '750e', '760', 'L7', '8 series', '840',
            'BMW Moto', 'F 800 GS', 'F 900 XR', 'G310 R', 'K 1300 S', 'M2', 'M3', 'M340',
            'M4', 'M440', 'M5', 'M6', 'M760E', 'M8', 'M850', 'X1', 'X2', 'X3', 'X4', 'X5',
            'X5 M', 'X6', 'X6 M', 'X7', 'XM SERIES', 'XM', 'XM 50e', 'Z4', 'I SERIES', 'i3',
            'i4', 'i5', 'i7', 'i7 M70', 'i8', 'iX', 'iX M60', 'iX1', 'iX3',
        ],
    ];

    public function run(): void
    {
        $languages = LangService::instance()->getLanguages();

        foreach ($this->brandsData as $brandName => $models) {
            $brand = $this->findOrCreateBrand($brandName, $languages);

            foreach ($models as $sort => $modelName) {
                $this->findOrCreateBrandModel($brand->id, $modelName, $sort + 1, $languages);
            }
        }
    }

    private function findOrCreateBrand(string $brandName, array $languages): Brand
    {
        $slug = Str::slug($brandName);

        $brand = Brand::withTrashed()->where('slug', $slug)->first();

        if ($brand) {
            return $brand;
        }

        $translations = $this->buildTranslations($brandName, $languages);

        return Brand::createWithLangs([
            'uuid' => Str::uuid(),
            'is_active' => true,
            'translations' => $translations,
        ]);
    }

    private function findOrCreateBrandModel(int $brandId, string $modelName, int $sort, array $languages): BrandModel
    {
        $existing = BrandModel::withTrashed()
            ->where('brand_id', $brandId)
            ->whereHas('translatable', fn($q) => $q->where('column', 'title')->where('text', $modelName))
            ->first();

        if ($existing) {
            return $existing;
        }

        $translations = $this->buildTranslations($modelName, $languages);

        return BrandModel::createWithLangs([
            'brand_id' => $brandId,
            'sort' => $sort,
            'is_active' => true,
            'translations' => $translations,
        ]);
    }

    private function buildTranslations(string $title, array $languages): array
    {
        return array_map(fn($lang) => [
            'title' => $title,
            'language_id' => $lang['id'],
        ], $languages);
    }
}
