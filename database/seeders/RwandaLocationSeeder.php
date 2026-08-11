<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Cast;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Seeds Rwanda's administrative divisions: 5 provinces, 30 districts, 416 sectors.
 *
 * Source: the NISR (National Institute of Statistics of Rwanda) official gazetteer
 * `rwa_gazetteer_rphc2012_v2.xlsx`, published as the UN OCHA Common Operational Dataset for
 * administrative boundaries (COD-AB RWA) at https://data.humdata.org/dataset/cod-ab-rwa.
 * Verified on 2026-08-11 against that workbook's ADM1/ADM2/ADM3 sheets, and cross-checked
 * against a second independent compilation of the hierarchy; the two agreed on all 30
 * districts and all 416 sectors.
 *
 * The `code` on every row is the official NISR P-code taken verbatim from that gazetteer —
 * `RW4` for Northern Province, `RW41` for Rulindo, `RW4116` for Rulindo's Shyorongi sector.
 * These are the identifiers other published Rwandan datasets key on, so they are what makes
 * re-seeding and future reconciliation possible without matching on names. Each P-code is
 * written out beside its name in the data array below rather than derived, so the mapping
 * stays auditable against the source workbook.
 *
 * One deliberate departure from the source: the gazetteer misspells Rulindo's `RW4116`
 * sector as "Shyrongi". The correct spelling, used here and in every other publication of
 * that sector, is "Shyorongi". The P-code is unaffected.
 *
 * Administrative divisions are occasionally revised by MINALOC, so this list must be
 * re-verified against the then-current official publication before launch, and again
 * whenever a territorial reform is announced.
 *
 * Sector names repeat across districts, so every sector is keyed on its district.
 */
final class RwandaLocationSeeder extends Seeder
{
    /**
     * Insert batch size for sectors.
     */
    private const int CHUNK_SIZE = 100;

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->divisions() as $provinceName => $province) {
                $provinceId = $this->upsertProvince($provinceName, $province['code']);

                foreach ($province['districts'] as $districtName => $district) {
                    $districtId = $this->upsertDistrict($provinceId, $districtName, $district['code']);

                    $this->upsertSectors($districtId, $district['sectors']);
                }
            }
        });
    }

    /**
     * Rwanda's administrative divisions with their official NISR P-codes.
     *
     * @return array<string, array{
     *     code: string,
     *     districts: array<string, array{code: string, sectors: array<string, string>}>
     * }>
     */
    private function divisions(): array
    {
        return [
            'Kigali City' => [
                'code' => 'RW1',
                'districts' => [
                    'Nyarugenge' => [
                        'code' => 'RW11',
                        'sectors' => [
                            'Gitega' => 'RW1101',
                            'Kanyinya' => 'RW1102',
                            'Kigali' => 'RW1103',
                            'Kimisagara' => 'RW1104',
                            'Mageregere' => 'RW1105',
                            'Muhima' => 'RW1106',
                            'Nyakabanda' => 'RW1107',
                            'Nyamirambo' => 'RW1108',
                            'Nyarugenge' => 'RW1109',
                            'Rwezamenyo' => 'RW1110',
                        ],
                    ],
                    'Gasabo' => [
                        'code' => 'RW12',
                        'sectors' => [
                            'Bumbogo' => 'RW1201',
                            'Gatsata' => 'RW1202',
                            'Gikomero' => 'RW1203',
                            'Gisozi' => 'RW1204',
                            'Jabana' => 'RW1205',
                            'Jali' => 'RW1206',
                            'Kacyiru' => 'RW1207',
                            'Kimihurura' => 'RW1208',
                            'Kimironko' => 'RW1209',
                            'Kinyinya' => 'RW1210',
                            'Ndera' => 'RW1211',
                            'Nduba' => 'RW1212',
                            'Remera' => 'RW1213',
                            'Rusororo' => 'RW1214',
                            'Rutunga' => 'RW1215',
                        ],
                    ],
                    'Kicukiro' => [
                        'code' => 'RW13',
                        'sectors' => [
                            'Gahanga' => 'RW1301',
                            'Gatenga' => 'RW1302',
                            'Gikondo' => 'RW1303',
                            'Kagarama' => 'RW1304',
                            'Kanombe' => 'RW1305',
                            'Kicukiro' => 'RW1306',
                            'Kigarama' => 'RW1307',
                            'Masaka' => 'RW1308',
                            'Niboye' => 'RW1309',
                            'Nyarugunga' => 'RW1310',
                        ],
                    ],
                ],
            ],
            'Southern Province' => [
                'code' => 'RW2',
                'districts' => [
                    'Nyanza' => [
                        'code' => 'RW21',
                        'sectors' => [
                            'Busasamana' => 'RW2101',
                            'Busoro' => 'RW2102',
                            'Cyabakamyi' => 'RW2103',
                            'Kibilizi' => 'RW2104',
                            'Kigoma' => 'RW2105',
                            'Mukingo' => 'RW2106',
                            'Muyira' => 'RW2107',
                            'Ntyazo' => 'RW2108',
                            'Nyagisozi' => 'RW2109',
                            'Rwabicuma' => 'RW2110',
                        ],
                    ],
                    'Gisagara' => [
                        'code' => 'RW22',
                        'sectors' => [
                            'Gikonko' => 'RW2201',
                            'Gishubi' => 'RW2202',
                            'Kansi' => 'RW2203',
                            'Kibirizi' => 'RW2204',
                            'Kigembe' => 'RW2205',
                            'Mamba' => 'RW2206',
                            'Muganza' => 'RW2207',
                            'Mugombwa' => 'RW2208',
                            'Mukindo' => 'RW2209',
                            'Musha' => 'RW2210',
                            'Ndora' => 'RW2211',
                            'Nyanza' => 'RW2212',
                            'Save' => 'RW2213',
                        ],
                    ],
                    'Nyaruguru' => [
                        'code' => 'RW23',
                        'sectors' => [
                            'Busanze' => 'RW2301',
                            'Cyahinda' => 'RW2302',
                            'Kibeho' => 'RW2303',
                            'Kivu' => 'RW2304',
                            'Mata' => 'RW2305',
                            'Muganza' => 'RW2306',
                            'Munini' => 'RW2307',
                            'Ngera' => 'RW2308',
                            'Ngoma' => 'RW2309',
                            'Nyabimata' => 'RW2310',
                            'Nyagisozi' => 'RW2311',
                            'Ruheru' => 'RW2312',
                            'Ruramba' => 'RW2313',
                            'Rusenge' => 'RW2314',
                        ],
                    ],
                    'Huye' => [
                        'code' => 'RW24',
                        'sectors' => [
                            'Gishamvu' => 'RW2401',
                            'Huye' => 'RW2402',
                            'Karama' => 'RW2403',
                            'Kigoma' => 'RW2404',
                            'Kinazi' => 'RW2405',
                            'Maraba' => 'RW2406',
                            'Mbazi' => 'RW2407',
                            'Mukura' => 'RW2408',
                            'Ngoma' => 'RW2409',
                            'Ruhashya' => 'RW2410',
                            'Rusatira' => 'RW2411',
                            'Rwaniro' => 'RW2412',
                            'Simbi' => 'RW2413',
                            'Tumba' => 'RW2414',
                        ],
                    ],
                    'Nyamagabe' => [
                        'code' => 'RW25',
                        'sectors' => [
                            'Buruhukiro' => 'RW2501',
                            'Cyanika' => 'RW2502',
                            'Gasaka' => 'RW2503',
                            'Gatare' => 'RW2504',
                            'Kaduha' => 'RW2505',
                            'Kamegeri' => 'RW2506',
                            'Kibirizi' => 'RW2507',
                            'Kibumbwe' => 'RW2508',
                            'Kitabi' => 'RW2509',
                            'Mbazi' => 'RW2510',
                            'Mugano' => 'RW2511',
                            'Musange' => 'RW2512',
                            'Musebeya' => 'RW2513',
                            'Mushubi' => 'RW2514',
                            'Nkomane' => 'RW2515',
                            'Tare' => 'RW2516',
                            'Uwinkingi' => 'RW2517',
                        ],
                    ],
                    'Ruhango' => [
                        'code' => 'RW26',
                        'sectors' => [
                            'Bweramana' => 'RW2601',
                            'Byimana' => 'RW2602',
                            'Kabagali' => 'RW2603',
                            'Kinazi' => 'RW2604',
                            'Kinihira' => 'RW2605',
                            'Mbuye' => 'RW2606',
                            'Mwendo' => 'RW2607',
                            'Ntongwe' => 'RW2608',
                            'Ruhango' => 'RW2609',
                        ],
                    ],
                    'Muhanga' => [
                        'code' => 'RW27',
                        'sectors' => [
                            'Cyeza' => 'RW2701',
                            'Kabacuzi' => 'RW2702',
                            'Kibangu' => 'RW2703',
                            'Kiyumba' => 'RW2704',
                            'Muhanga' => 'RW2705',
                            'Mushishiro' => 'RW2706',
                            'Nyabinoni' => 'RW2707',
                            'Nyamabuye' => 'RW2708',
                            'Nyarusange' => 'RW2709',
                            'Rongi' => 'RW2710',
                            'Rugendabari' => 'RW2711',
                            'Shyogwe' => 'RW2712',
                        ],
                    ],
                    'Kamonyi' => [
                        'code' => 'RW28',
                        'sectors' => [
                            'Gacurabwenge' => 'RW2801',
                            'Karama' => 'RW2802',
                            'Kayenzi' => 'RW2803',
                            'Kayumbu' => 'RW2804',
                            'Mugina' => 'RW2805',
                            'Musambira' => 'RW2806',
                            'Ngamba' => 'RW2807',
                            'Nyamiyaga' => 'RW2808',
                            'Nyarubaka' => 'RW2809',
                            'Rugarika' => 'RW2810',
                            'Rukoma' => 'RW2811',
                            'Runda' => 'RW2812',
                        ],
                    ],
                ],
            ],
            'Western Province' => [
                'code' => 'RW3',
                'districts' => [
                    'Karongi' => [
                        'code' => 'RW31',
                        'sectors' => [
                            'Bwishyura' => 'RW3101',
                            'Gashari' => 'RW3102',
                            'Gishyita' => 'RW3103',
                            'Gitesi' => 'RW3104',
                            'Mubuga' => 'RW3105',
                            'Murambi' => 'RW3106',
                            'Murundi' => 'RW3107',
                            'Mutuntu' => 'RW3108',
                            'Rubengera' => 'RW3109',
                            'Rugabano' => 'RW3110',
                            'Ruganda' => 'RW3111',
                            'Rwankuba' => 'RW3112',
                            'Twumba' => 'RW3113',
                        ],
                    ],
                    'Rutsiro' => [
                        'code' => 'RW32',
                        'sectors' => [
                            'Boneza' => 'RW3201',
                            'Gihango' => 'RW3202',
                            'Kigeyo' => 'RW3203',
                            'Kivumu' => 'RW3204',
                            'Manihira' => 'RW3205',
                            'Mukura' => 'RW3206',
                            'Murunda' => 'RW3207',
                            'Musasa' => 'RW3208',
                            'Mushonyi' => 'RW3209',
                            'Mushubati' => 'RW3210',
                            'Nyabirasi' => 'RW3211',
                            'Ruhango' => 'RW3212',
                            'Rusebeya' => 'RW3213',
                        ],
                    ],
                    'Rubavu' => [
                        'code' => 'RW33',
                        'sectors' => [
                            'Bugeshi' => 'RW3301',
                            'Busasamana' => 'RW3302',
                            'Cyanzarwe' => 'RW3303',
                            'Gisenyi' => 'RW3304',
                            'Kanama' => 'RW3305',
                            'Kanzenze' => 'RW3306',
                            'Mudende' => 'RW3307',
                            'Nyakiriba' => 'RW3308',
                            'Nyamyumba' => 'RW3309',
                            'Nyundo' => 'RW3310',
                            'Rubavu' => 'RW3311',
                            'Rugerero' => 'RW3312',
                        ],
                    ],
                    'Nyabihu' => [
                        'code' => 'RW34',
                        'sectors' => [
                            'Bigogwe' => 'RW3401',
                            'Jenda' => 'RW3402',
                            'Jomba' => 'RW3403',
                            'Kabatwa' => 'RW3404',
                            'Karago' => 'RW3405',
                            'Kintobo' => 'RW3406',
                            'Mukamira' => 'RW3407',
                            'Muringa' => 'RW3408',
                            'Rambura' => 'RW3409',
                            'Rugera' => 'RW3410',
                            'Rurembo' => 'RW3411',
                            'Shyira' => 'RW3412',
                        ],
                    ],
                    'Ngororero' => [
                        'code' => 'RW35',
                        'sectors' => [
                            'Bwira' => 'RW3501',
                            'Gatumba' => 'RW3502',
                            'Hindiro' => 'RW3503',
                            'Kabaya' => 'RW3504',
                            'Kageyo' => 'RW3505',
                            'Kavumu' => 'RW3506',
                            'Matyazo' => 'RW3507',
                            'Muhanda' => 'RW3508',
                            'Muhororo' => 'RW3509',
                            'Ndaro' => 'RW3510',
                            'Ngororero' => 'RW3511',
                            'Nyange' => 'RW3512',
                            'Sovu' => 'RW3513',
                        ],
                    ],
                    'Rusizi' => [
                        'code' => 'RW36',
                        'sectors' => [
                            'Bugarama' => 'RW3601',
                            'Butare' => 'RW3602',
                            'Bweyeye' => 'RW3603',
                            'Gashonga' => 'RW3604',
                            'Giheke' => 'RW3605',
                            'Gihundwe' => 'RW3606',
                            'Gikundamvura' => 'RW3607',
                            'Gitambi' => 'RW3608',
                            'Kamembe' => 'RW3609',
                            'Muganza' => 'RW3610',
                            'Mururu' => 'RW3611',
                            'Nkanka' => 'RW3612',
                            'Nkombo' => 'RW3613',
                            'Nkungu' => 'RW3614',
                            'Nyakabuye' => 'RW3615',
                            'Nyakarenzo' => 'RW3616',
                            'Nzahaha' => 'RW3617',
                            'Rwimbogo' => 'RW3618',
                        ],
                    ],
                    'Nyamasheke' => [
                        'code' => 'RW37',
                        'sectors' => [
                            'Bushekeri' => 'RW3701',
                            'Bushenge' => 'RW3702',
                            'Cyato' => 'RW3703',
                            'Gihombo' => 'RW3704',
                            'Kagano' => 'RW3705',
                            'Kanjongo' => 'RW3706',
                            'Karambi' => 'RW3707',
                            'Karengera' => 'RW3708',
                            'Kirimbi' => 'RW3709',
                            'Macuba' => 'RW3710',
                            'Mahembe' => 'RW3711',
                            'Nyabitekeri' => 'RW3712',
                            'Rangiro' => 'RW3713',
                            'Ruharambuga' => 'RW3714',
                            'Shangi' => 'RW3715',
                        ],
                    ],
                ],
            ],
            'Northern Province' => [
                'code' => 'RW4',
                'districts' => [
                    'Rulindo' => [
                        'code' => 'RW41',
                        'sectors' => [
                            'Base' => 'RW4101',
                            'Burega' => 'RW4102',
                            'Bushoki' => 'RW4103',
                            'Buyoga' => 'RW4104',
                            'Cyinzuzi' => 'RW4105',
                            'Cyungo' => 'RW4106',
                            'Kinihira' => 'RW4107',
                            'Kisaro' => 'RW4108',
                            'Masoro' => 'RW4109',
                            'Mbogo' => 'RW4110',
                            'Murambi' => 'RW4111',
                            'Ngoma' => 'RW4112',
                            'Ntarabana' => 'RW4113',
                            'Rukozo' => 'RW4114',
                            'Rusiga' => 'RW4115',
                            'Shyorongi' => 'RW4116',
                            'Tumba' => 'RW4117',
                        ],
                    ],
                    'Gakenke' => [
                        'code' => 'RW42',
                        'sectors' => [
                            'Busengo' => 'RW4201',
                            'Coko' => 'RW4202',
                            'Cyabingo' => 'RW4203',
                            'Gakenke' => 'RW4204',
                            'Gashenyi' => 'RW4205',
                            'Janja' => 'RW4206',
                            'Kamubuga' => 'RW4207',
                            'Karambo' => 'RW4208',
                            'Kivuruga' => 'RW4209',
                            'Mataba' => 'RW4210',
                            'Minazi' => 'RW4211',
                            'Mugunga' => 'RW4212',
                            'Muhondo' => 'RW4213',
                            'Muyongwe' => 'RW4214',
                            'Muzo' => 'RW4215',
                            'Nemba' => 'RW4216',
                            'Ruli' => 'RW4217',
                            'Rusasa' => 'RW4218',
                            'Rushashi' => 'RW4219',
                        ],
                    ],
                    'Musanze' => [
                        'code' => 'RW43',
                        'sectors' => [
                            'Busogo' => 'RW4301',
                            'Cyuve' => 'RW4302',
                            'Gacaca' => 'RW4303',
                            'Gashaki' => 'RW4304',
                            'Gataraga' => 'RW4305',
                            'Kimonyi' => 'RW4306',
                            'Kinigi' => 'RW4307',
                            'Muhoza' => 'RW4308',
                            'Muko' => 'RW4309',
                            'Musanze' => 'RW4310',
                            'Nkotsi' => 'RW4311',
                            'Nyange' => 'RW4312',
                            'Remera' => 'RW4313',
                            'Rwaza' => 'RW4314',
                            'Shingiro' => 'RW4315',
                        ],
                    ],
                    'Burera' => [
                        'code' => 'RW44',
                        'sectors' => [
                            'Bungwe' => 'RW4401',
                            'Butaro' => 'RW4402',
                            'Cyanika' => 'RW4403',
                            'Cyeru' => 'RW4404',
                            'Gahunga' => 'RW4405',
                            'Gatebe' => 'RW4406',
                            'Gitovu' => 'RW4407',
                            'Kagogo' => 'RW4408',
                            'Kinoni' => 'RW4409',
                            'Kinyababa' => 'RW4410',
                            'Kivuye' => 'RW4411',
                            'Nemba' => 'RW4412',
                            'Rugarama' => 'RW4413',
                            'Rugengabari' => 'RW4414',
                            'Ruhunde' => 'RW4415',
                            'Rusarabuye' => 'RW4416',
                            'Rwerere' => 'RW4417',
                        ],
                    ],
                    'Gicumbi' => [
                        'code' => 'RW45',
                        'sectors' => [
                            'Bukure' => 'RW4501',
                            'Bwisige' => 'RW4502',
                            'Byumba' => 'RW4503',
                            'Cyumba' => 'RW4504',
                            'Giti' => 'RW4505',
                            'Kageyo' => 'RW4506',
                            'Kaniga' => 'RW4507',
                            'Manyagiro' => 'RW4508',
                            'Miyove' => 'RW4509',
                            'Mukarange' => 'RW4510',
                            'Muko' => 'RW4511',
                            'Mutete' => 'RW4512',
                            'Nyamiyaga' => 'RW4513',
                            'Nyankenke' => 'RW4514',
                            'Rubaya' => 'RW4515',
                            'Rukomo' => 'RW4516',
                            'Rushaki' => 'RW4517',
                            'Rutare' => 'RW4518',
                            'Ruvune' => 'RW4519',
                            'Rwamiko' => 'RW4520',
                            'Shangasha' => 'RW4521',
                        ],
                    ],
                ],
            ],
            'Eastern Province' => [
                'code' => 'RW5',
                'districts' => [
                    'Rwamagana' => [
                        'code' => 'RW51',
                        'sectors' => [
                            'Fumbwe' => 'RW5101',
                            'Gahengeri' => 'RW5102',
                            'Gishali' => 'RW5103',
                            'Karenge' => 'RW5104',
                            'Kigabiro' => 'RW5105',
                            'Muhazi' => 'RW5106',
                            'Munyaga' => 'RW5107',
                            'Munyiginya' => 'RW5108',
                            'Musha' => 'RW5109',
                            'Muyumbu' => 'RW5110',
                            'Mwulire' => 'RW5111',
                            'Nyakaliro' => 'RW5112',
                            'Nzige' => 'RW5113',
                            'Rubona' => 'RW5114',
                        ],
                    ],
                    'Nyagatare' => [
                        'code' => 'RW52',
                        'sectors' => [
                            'Gatunda' => 'RW5201',
                            'Karama' => 'RW5202',
                            'Karangazi' => 'RW5203',
                            'Katabagemu' => 'RW5204',
                            'Kiyombe' => 'RW5205',
                            'Matimba' => 'RW5206',
                            'Mimuri' => 'RW5207',
                            'Mukama' => 'RW5208',
                            'Musheri' => 'RW5209',
                            'Nyagatare' => 'RW5210',
                            'Rukomo' => 'RW5211',
                            'Rwempasha' => 'RW5212',
                            'Rwimiyaga' => 'RW5213',
                            'Tabagwe' => 'RW5214',
                        ],
                    ],
                    'Gatsibo' => [
                        'code' => 'RW53',
                        'sectors' => [
                            'Gasange' => 'RW5301',
                            'Gatsibo' => 'RW5302',
                            'Gitoki' => 'RW5303',
                            'Kabarore' => 'RW5304',
                            'Kageyo' => 'RW5305',
                            'Kiramuruzi' => 'RW5306',
                            'Kiziguro' => 'RW5307',
                            'Muhura' => 'RW5308',
                            'Murambi' => 'RW5309',
                            'Ngarama' => 'RW5310',
                            'Nyagihanga' => 'RW5311',
                            'Remera' => 'RW5312',
                            'Rugarama' => 'RW5313',
                            'Rwimbogo' => 'RW5314',
                        ],
                    ],
                    'Kayonza' => [
                        'code' => 'RW54',
                        'sectors' => [
                            'Gahini' => 'RW5401',
                            'Kabare' => 'RW5402',
                            'Kabarondo' => 'RW5403',
                            'Mukarange' => 'RW5404',
                            'Murama' => 'RW5405',
                            'Murundi' => 'RW5406',
                            'Mwiri' => 'RW5407',
                            'Ndego' => 'RW5408',
                            'Nyamirama' => 'RW5409',
                            'Rukara' => 'RW5410',
                            'Ruramira' => 'RW5411',
                            'Rwinkwavu' => 'RW5412',
                        ],
                    ],
                    'Kirehe' => [
                        'code' => 'RW55',
                        'sectors' => [
                            'Gahara' => 'RW5501',
                            'Gatore' => 'RW5502',
                            'Kigarama' => 'RW5503',
                            'Kigina' => 'RW5504',
                            'Kirehe' => 'RW5505',
                            'Mahama' => 'RW5506',
                            'Mpanga' => 'RW5507',
                            'Musaza' => 'RW5508',
                            'Mushikiri' => 'RW5509',
                            'Nasho' => 'RW5510',
                            'Nyamugari' => 'RW5511',
                            'Nyarubuye' => 'RW5512',
                        ],
                    ],
                    'Ngoma' => [
                        'code' => 'RW56',
                        'sectors' => [
                            'Gashanda' => 'RW5601',
                            'Jarama' => 'RW5602',
                            'Karembo' => 'RW5603',
                            'Kazo' => 'RW5604',
                            'Kibungo' => 'RW5605',
                            'Mugesera' => 'RW5606',
                            'Murama' => 'RW5607',
                            'Mutenderi' => 'RW5608',
                            'Remera' => 'RW5609',
                            'Rukira' => 'RW5610',
                            'Rukumberi' => 'RW5611',
                            'Rurenge' => 'RW5612',
                            'Sake' => 'RW5613',
                            'Zaza' => 'RW5614',
                        ],
                    ],
                    'Bugesera' => [
                        'code' => 'RW57',
                        'sectors' => [
                            'Gashora' => 'RW5701',
                            'Juru' => 'RW5702',
                            'Kamabuye' => 'RW5703',
                            'Mareba' => 'RW5704',
                            'Mayange' => 'RW5705',
                            'Musenyi' => 'RW5706',
                            'Mwogo' => 'RW5707',
                            'Ngeruka' => 'RW5708',
                            'Ntarama' => 'RW5709',
                            'Nyamata' => 'RW5710',
                            'Nyarugenge' => 'RW5711',
                            'Rilima' => 'RW5712',
                            'Ruhuha' => 'RW5713',
                            'Rweru' => 'RW5714',
                            'Shyara' => 'RW5715',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function upsertProvince(string $name, string $code): int
    {
        $existing = $this->matchExisting(
            DB::table('provinces')->where('code', $code)->orWhere('name', $name)->get(),
            $code,
            $name,
        );

        if ($existing instanceof stdClass) {
            $this->applyChanges('provinces', $existing, [
                'name' => $name,
                'code' => $code,
            ]);

            return Cast::int($existing->id);
        }

        return (int) DB::table('provinces')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'name' => $name,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertDistrict(int $provinceId, string $name, string $code): int
    {
        $existing = $this->matchExisting(
            DB::table('districts')
                ->where('code', $code)
                ->orWhere(fn (Builder $query): Builder => $query->where('province_id', $provinceId)->where('name', $name))
                ->get(),
            $code,
            $name,
        );

        if ($existing instanceof stdClass) {
            $this->applyChanges('districts', $existing, [
                'province_id' => $provinceId,
                'name' => $name,
                'code' => $code,
            ]);

            return Cast::int($existing->id);
        }

        return (int) DB::table('districts')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'province_id' => $provinceId,
            'name' => $name,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $sectors  Sector name => official NISR P-code.
     */
    private function upsertSectors(int $districtId, array $sectors): void
    {
        $existing = DB::table('sectors')->where('district_id', $districtId)->get();
        $now = now();
        $pending = [];

        foreach ($sectors as $name => $code) {
            $match = $this->matchExisting($existing, $code, $name);

            if ($match instanceof stdClass) {
                $this->applyChanges('sectors', $match, [
                    'name' => $name,
                    'code' => $code,
                ]);

                continue;
            }

            $pending[] = [
                'uuid' => Str::uuid()->toString(),
                'district_id' => $districtId,
                'name' => $name,
                'code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($pending, self::CHUNK_SIZE) as $chunk) {
            DB::table('sectors')->insert($chunk);
        }
    }

    /**
     * Locate an already-seeded row, preferring the P-code but falling back to the name.
     *
     * The name fallback is what lets a database seeded under an older coding scheme be
     * migrated in place — the row is found by name and has its `code` corrected — instead of
     * every division being re-inserted as a duplicate.
     *
     * @param  Collection<int, stdClass>  $candidates
     */
    private function matchExisting(Collection $candidates, string $code, string $name): ?stdClass
    {
        return $candidates->firstWhere('code', $code)
            ?? $candidates->firstWhere('name', $name);
    }

    /**
     * Update only the columns that actually drifted, leaving `uuid` untouched so identifiers
     * already referenced elsewhere survive a re-seed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyChanges(string $table, stdClass $row, array $attributes): void
    {
        $changes = array_filter(
            $attributes,
            // Compared as strings: the driver may hand back integer columns as either
            // `int` or numeric `string`, and a strict compare would then report drift on
            // every run and rewrite untouched rows.
            fn (mixed $value, string $column): bool => Cast::string($row->{$column} ?? '') !== Cast::string($value),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes === []) {
            return;
        }

        DB::table($table)->where('id', $row->id)->update($changes + ['updated_at' => now()]);
    }
}
