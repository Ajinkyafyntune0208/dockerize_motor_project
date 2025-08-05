<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\MasterIndustryType;

class MasterIndustryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MasterIndustryType::truncate();
        MasterIndustryType::insert([
            [
                "company_alias" => "nic",
                "value" => "Oilseed Farm",
                "code" => "11111201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Wheat Farm",
                "code" => "11111401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Corn Farm",
                "code" => "11111501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rice Farm",
                "code" => "11111601",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Grain Farm",
                "code" => "11111991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Potato Farm",
                "code" => "11112111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Citrus Groves",
                "code" => "11113201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Apple Orchards",
                "code" => "11113311",
            ],
            [
                "company_alias" => "nic",
                "value" => "Grape Vineyards",
                "code" => "11113321",
            ],
            [
                "company_alias" => "nic",
                "value" => "Strawberry Farm",
                "code" => "11113331",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Noncitrus Fruit Farm",
                "code" => "11113392",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mushroom",
                "code" => "11114111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Food Crops Grown Under Cover",
                "code" => "11114191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nursery, Tree",
                "code" => "11114211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Floriculture",
                "code" => "11114221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nursery, Floriculture",
                "code" => "11114291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Greenhouse, Nurseryand Floriculture",
                "code" => "11114991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tobacco Farm",
                "code" => "11119101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cotton Farm",
                "code" => "11119201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sugarcane Farm",
                "code" => "11119301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Peanut Farm",
                "code" => "11119921",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Crop Farm",
                "code" => "11119991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Crop",
                "code" => "11119992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cattle Feedlots",
                "code" => "11121121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dairy Cattle, Milk",
                "code" => "11121201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Chicken Egg",
                "code" => "11123101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Broilers/Meat Type Chicken",
                "code" => "11123201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Poultry Hatcheries",
                "code" => "11123401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Poultry",
                "code" => "11123901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Poultry, Egg",
                "code" => "11123992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sheep Farm",
                "code" => "11124101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Goat Farm",
                "code" => "11124201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Animal Aquaculture",
                "code" => "11125191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fur-Bearing Animal, Rabbit",
                "code" => "11129301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Animal",
                "code" => "11129993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Forest Nurseries",
                "code" => "11132101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Logging",
                "code" => "11133101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Forestry, Logging",
                "code" => "11139991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fishing",
                "code" => "11141191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cotton Ginning",
                "code" => "11151111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Crop Machine Harvesting",
                "code" => "11151131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Farm Mgmt Services",
                "code" => "11151161",
            ],
            [
                "company_alias" => "nic",
                "value" => "Support - Animal",
                "code" => "11152101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Support - Agri, Forestry",
                "code" => "11159991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Agri, Forestry",
                "code" => "11199991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Oil, Gas Extraction",
                "code" => "12111191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bituminous Coal/Lignite Mining",
                "code" => "12121111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bituminous Coal U/G Mining",
                "code" => "12121121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Coal Mining",
                "code" => "12121191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Iron Ore Mining",
                "code" => "12122101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Gold Ore Mining",
                "code" => "12122211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Silver Ore Mining",
                "code" => "12122221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Copper, Nickel, Lead, Zinc Mining",
                "code" => "12122391",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Metal Ore Mining",
                "code" => "12122991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Metal Ore Mining",
                "code" => "12122992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Stone Mining, Quarrying",
                "code" => "12123192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial Sand Mining",
                "code" => "12123221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sand, Gravel, Ceramic etc",
                "code" => "12123291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nonmetallic Mineral Mining",
                "code" => "12123993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Drilling Oil, Gas Wells",
                "code" => "12131111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Support - Oil, Gas Operations",
                "code" => "12131121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mining",
                "code" => "12199991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hydroelectric Power",
                "code" => "12211111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fossil Fuel Electric Power",
                "code" => "12211121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nuclear Electric Power",
                "code" => "12211131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electric Power",
                "code" => "12211191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Electric Power",
                "code" => "12211192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electric Power Distb",
                "code" => "12211221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Power Trans, Control, Distb",
                "code" => "12211291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Natural Gas Distb",
                "code" => "12212101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Water Supply, Irrigatio",
                "code" => "12213101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sewage Treatment Facilities",
                "code" => "12213201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Water, Sewage",
                "code" => "12213991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dental Equip, Supplies Mfg",
                "code" => "13391141",
            ],
            [
                "company_alias" => "nic",
                "value" => "Leather, Hide Tanning, Finishing",
                "code" => "13161101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rubber, Plastics Footwear Mfg",
                "code" => "13162111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Footwear Mfg",
                "code" => "13162191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Footwear Mfg",
                "code" => "13162192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Luggage Mfg",
                "code" => "13169911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Leather, Allied Prod Mfg",
                "code" => "13169992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sawmills",
                "code" => "13211131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sawmills, Wood Preservation",
                "code" => "13211191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Softwood Veneer, Plywood Mfg",
                "code" => "13212121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Veneer, Plywood, Engg Wood Prod Mfg",
                "code" => "13212192",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Misc Wood Prod Mfg",
                "code" => "13219991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Wood Prod Mfg",
                "code" => "13219993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pulp Mills",
                "code" => "13221101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paper (except Newsprint] Mills",
                "code" => "13221211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Newsprint Mills",
                "code" => "13221221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paper Mills",
                "code" => "13221291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paperboard Mills",
                "code" => "13221301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pulp, Paper, Paperboard Mills",
                "code" => "13221991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Corrugated, Solid Fiber Box Mfg",
                "code" => "13222111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics, Foil, Coated Paper Bag Mfg",
                "code" => "13222231",
            ],
            [
                "company_alias" => "nic",
                "value" => "Laminated Aluminum Foil Mfg",
                "code" => "13222251",
            ],
            [
                "company_alias" => "nic",
                "value" => "Envelope Mfg",
                "code" => "13222321",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sty Prod Mfg",
                "code" => "13222391",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paper Mfg",
                "code" => "13229991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Digital Printing",
                "code" => "13231151",
            ],
            [
                "company_alias" => "nic",
                "value" => "Books Printing",
                "code" => "13231171",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Comm Printing",
                "code" => "13231191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Printing",
                "code" => "13231192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Printing, Related Support Activities",
                "code" => "13231991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petro Refineries",
                "code" => "13241101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petro Lubricating Oil, Grease Mfg",
                "code" => "13241911",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Petro, Coal Prods Mfg",
                "code" => "13241991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petro, Coal Prods Mfg",
                "code" => "13241993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petrochemical Mfg",
                "code" => "13251101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial Gas Mfg",
                "code" => "13251201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Inorganic Dye, Pigment Mfg",
                "code" => "13251311",
            ],
            [
                "company_alias" => "nic",
                "value" => "Synthetic Dye, Pigment Mfg",
                "code" => "13251391",
            ],
            [
                "company_alias" => "nic",
                "value" => "Alkalies, Chlorine Mfg",
                "code" => "13251811",
            ],
            [
                "company_alias" => "nic",
                "value" => "Carbon Black Mfg",
                "code" => "13251821",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Basic Inorganic Chemical Mfg",
                "code" => "13251891",
            ],
            [
                "company_alias" => "nic",
                "value" => "Gum, Wood Chemical Mfg",
                "code" => "13251911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ethyl Alcohol Mfg",
                "code" => "13251931",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Basic Organic Chemical Mfg",
                "code" => "13251991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Basic Chemical Mfg",
                "code" => "13251992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Material, Resin Mfg",
                "code" => "13252111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Synthetic Rubber Mfg",
                "code" => "13252121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cellulosic Organic Fiber Mfg",
                "code" => "13252211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Artificial, Synthetic Fibers, Filaments Mfg",
                "code" => "13252291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fertilizer Mfg",
                "code" => "13253191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pesticide, Fertilizerand Other Agri Chemical Mfg",
                "code" => "13253991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pharmaceutical, Medicine Mfg",
                "code" => "13254191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paint, Coating Mfg",
                "code" => "13255101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Adhesive Mfg",
                "code" => "13255201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paint, Coating, Adhesive Mfg",
                "code" => "13255991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Soap, Other Detergent Mfg",
                "code" => "13256111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Soap, Toilet Preparation Mfg",
                "code" => "13256991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Printing Ink Mfg",
                "code" => "13259101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Explosives Mfg",
                "code" => "13259201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Photographic Film, Paper, Plate and Chemical Mfg",
                "code" => "13259921",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Chemical Prod, Preparation Mfg",
                "code" => "13259991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Chemical Mfg",
                "code" => "13259992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Bag Mfg",
                "code" => "13261111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cut Stone, Stone Prod Mfg",
                "code" => "13279911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Packaging Film, Sheet (including Laminated] Mfg",
                "code" => "13261121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Packaging, Unlaminated Film, Sheet Mfg",
                "code" => "13261191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Utilities",
                "code" => "12219991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Residential Bldg Cons",
                "code" => "12361191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cons of Bldgs",
                "code" => "12369991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Water/Sewer Line Cons",
                "code" => "12371101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Oil/Gas Pipeline Cons",
                "code" => "12371201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Power/Communication Line",
                "code" => "12371301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Highway, Street, Bridge Cons",
                "code" => "12373101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Heavy, Civil Engg Cons",
                "code" => "12379991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electrical Contractors",
                "code" => "12382101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plumbing, Heating, A/c",
                "code" => "12382201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Splty Trade Contractors",
                "code" => "12389993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Animal Food Manufacturing",
                "code" => "13111191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Flour Milling",
                "code" => "13112111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rice Milling",
                "code" => "13112121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Soybean Processing",
                "code" => "13112221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fats, Oils Refining, Blending",
                "code" => "13112251",
            ],
            [
                "company_alias" => "nic",
                "value" => "Grain, Oilseed Milling",
                "code" => "13112991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sugarcane Mills",
                "code" => "13113111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sugar Mfg",
                "code" => "13113191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sugar, Confectionery Prod Mfg",
                "code" => "13113991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Frozen Food Mfg",
                "code" => "13114191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fruit, Veg Canning",
                "code" => "13114211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dried, Dehydrated Food Mfg",
                "code" => "13114231",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fruit, Veg Canning, Pickling, Drying",
                "code" => "13114291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dairy Prod Mfg except Frozen",
                "code" => "13115191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ice Cream Mfg",
                "code" => "13115201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dairy Prod Mfg",
                "code" => "13115991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Meat Processed from Carcasses",
                "code" => "13116121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Poultry",
                "code" => "13116151",
            ],
            [
                "company_alias" => "nic",
                "value" => "Animal Slaughtering",
                "code" => "13116191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Seafood Canning",
                "code" => "13117111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fresh, Frozen Seafood",
                "code" => "13117121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Seafood Prod Preparation, Packaging",
                "code" => "13117191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Retail Bakeries",
                "code" => "13118111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bread, Bakery Prod Mfg",
                "code" => "13118191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Snack Food Mfg",
                "code" => "13119192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Coffee, Tea Mfg",
                "code" => "13119201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Flavoring Syrup, Concentrate Mfg",
                "code" => "13119301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Spice, Extract Mfg",
                "code" => "13119421",
            ],
            [
                "company_alias" => "nic",
                "value" => "Perishable Prepared Food Mfg",
                "code" => "13119911",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Food Mfg",
                "code" => "13119991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Soft Drink Mfg",
                "code" => "13121111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bottled Water Mfg",
                "code" => "13121121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ice Mfg",
                "code" => "13121131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Breweries",
                "code" => "13121201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Wineries",
                "code" => "13121301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Distilleries",
                "code" => "13121401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Beverage Mfg",
                "code" => "13121991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tobacco Stemming, Redrying",
                "code" => "13122101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cigarette Mfg",
                "code" => "13122211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tobacco Prod Mfg",
                "code" => "13122292",
            ],
            [
                "company_alias" => "nic",
                "value" => "Yarn Spinning Mills",
                "code" => "13131111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Yarn Texture, Throw, Twist Mills",
                "code" => "13131121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Thread Mills",
                "code" => "13131131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fiber, Yarn, Thread Mills",
                "code" => "13131191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Knit Fabric Mills",
                "code" => "13132491",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fabric Mills",
                "code" => "13132991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Textile, Fabric Finishing Mills",
                "code" => "13133191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Textile, Fabric Finishing, Fabric Coating Mills",
                "code" => "13133991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Textile Mills",
                "code" => "13139991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Carpet, Rug Mills",
                "code" => "13141101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Curtain, Drapery Mills",
                "code" => "13141211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Household Textile Prod Mills",
                "code" => "13141292",
            ],
            [
                "company_alias" => "nic",
                "value" => "Textile Furnishings Mills",
                "code" => "13141991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tire Cord, Tire Fabric Mills",
                "code" => "13149921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Textile Prod Mills",
                "code" => "13149994",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hosiery, Sock Mills",
                "code" => "13151191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Apparel Knitting Mills",
                "code" => "13151991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fur, Leather Apparel Mfg",
                "code" => "13152921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Apparel Mfg",
                "code" => "13159993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Pipe, Pipe Fitting, Unlaminated Profile Shape Mfg",
                "code" => "13261291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Polystyrene Foam Prod Mfg",
                "code" => "13261401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plastics Bottle Mfg",
                "code" => "13261601",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Plastics Prod Mfg",
                "code" => "13261992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tire Mfg (except Retreading]",
                "code" => "13262111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tire Retreading",
                "code" => "13262121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rubber Prod Mfg",
                "code" => "13262993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pottery, Ceramics, Plumbing Fixture Mfg",
                "code" => "13271191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ceramic Wall, Floor Tile Mfg",
                "code" => "13271221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Clay Refractory Mfg",
                "code" => "13271241",
            ],
            [
                "company_alias" => "nic",
                "value" => "Glass, Glass Prod Mfg",
                "code" => "13272191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cement Mfg",
                "code" => "13273101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cement, Concrete Prod Mfg",
                "code" => "13273991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Lime Mfg",
                "code" => "13274101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Gypsum Prod Mfg",
                "code" => "13274201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Lime, Gypsum Prod Mfg",
                "code" => "13274991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Abrasive Prod Mfg",
                "code" => "13279101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Nonmetallic Mineral Prod Mfg",
                "code" => "13279994",
            ],
            [
                "company_alias" => "nic",
                "value" => "Iron, Steel Mills",
                "code" => "13311111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rolled Steel Shape Mfg",
                "code" => "13312211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Steel Wire Drawing",
                "code" => "13312221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rolling, Drawing of Purchased Steel",
                "code" => "13312291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Steel Prod Mfg from Purchased Steel",
                "code" => "13312991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Alumina Refining",
                "code" => "13313111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Alumina, Aluminum",
                "code" => "13313191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Copper Wire Drawing",
                "code" => "13314221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Copper Rolling, Drawing, Extruding Alloying",
                "code" => "13314291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nonferrous Metal Rolling, Drawing, Extruding, Alloying",
                "code" => "13314992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Iron Foundries",
                "code" => "13315111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Steel Invs Foundries",
                "code" => "13315121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ferrous Metal Foundries",
                "code" => "13315191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Foundries",
                "code" => "13315991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Iron, Steel Forging",
                "code" => "13321111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Forging, Stamping",
                "code" => "13321191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Kitchen Utensil, Potand Pan Mfg",
                "code" => "13322141",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cutlery, Handtool Mfg",
                "code" => "13322191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Plate Work, Fab Strl Prod Mfg",
                "code" => "13323191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sheet Metal Work Mfg",
                "code" => "13323221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Power Boiler, Heat Exchanger Mfg",
                "code" => "13324101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Metal Can, Box Container Light Gauge",
                "code" => "13324391",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Metal Container Mfg",
                "code" => "13324392",
            ],
            [
                "company_alias" => "nic",
                "value" => "Boiler, Tank, Shipping Container Mfg",
                "code" => "13324991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hardware Mfg",
                "code" => "13325101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Spring, Wire Prod Mfg",
                "code" => "13326191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Machine Shops",
                "code" => "13327101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bolt, Nut, Screw, Rivetand Washer Mfg",
                "code" => "13327221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Turned Prod, Screw, Nutand Bolt Mfg",
                "code" => "13327291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Machine Shops, Turned Prod, Screw, Nut/Bolt Mfg",
                "code" => "13327991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Metal Coating, Engraving non Jewelry/Silverware",
                "code" => "13328121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electroplating, Plating, Polishing",
                "code" => "13328131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Coating, Engraving, Heat Treating",
                "code" => "13328191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial Valve Mfg",
                "code" => "13329111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Metal Valve Mfg",
                "code" => "13329191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ball, Roller Bearing Mfg",
                "code" => "13329911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Small Arms Ammunition Mfg",
                "code" => "13329921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ammunition non Small Arms Mfg",
                "code" => "13329931",
            ],
            [
                "company_alias" => "nic",
                "value" => "Small Arms Mfg",
                "code" => "13329941",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fab Pipe, Pipe Fitting Mfg",
                "code" => "13329961",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Misc Fab Metal Prod Mfg",
                "code" => "13329992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Farm M/c, Equip Mfg",
                "code" => "13331111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Soybean Farm",
                "code" => "11111101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Agri, Cons, Mining M/c Mfg",
                "code" => "13331991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial M/c Mfg",
                "code" => "13332991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Industrial M/c Mfg",
                "code" => "13332992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Office M/c Mfg",
                "code" => "13333131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Optical Inst, Lens Mfg",
                "code" => "13333141",
            ],
            [
                "company_alias" => "nic",
                "value" => "Heating, A/c, Comm Refrigeration Equip Mfg",
                "code" => "13334191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Machine Tool Metal Cutting Types Mfg",
                "code" => "13335121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Spl Die, Tool, Die Set, Jigand Fixture Mfg",
                "code" => "13335141",
            ],
            [
                "company_alias" => "nic",
                "value" => "Turbine, Generator Set Units Mfg",
                "code" => "13336111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mechanical Power Trans Equip Mfg",
                "code" => "13336131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Engine, Turbine, Power Trans Equip Mfg",
                "code" => "13336191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Material Handling Equip Mfg",
                "code" => "13339291",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Misc General Purpose M/c Mfg",
                "code" => "13339992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electronic Comp Mfg",
                "code" => "13341111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp Storage Device Mfg",
                "code" => "13341121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp, Peripheral Equip Mfg",
                "code" => "13341191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Telephone Apparatus Mfg",
                "code" => "13342101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Radio, TV, Wireless Equip Mfg",
                "code" => "13342201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Communications Equip Mfg",
                "code" => "13342991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Audio, Video Equip Mfg",
                "code" => "13343101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electron Tube Mfg",
                "code" => "13344111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electronic Coil, Transformer Mfg",
                "code" => "13344161",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Electronic Component Mfg",
                "code" => "13344191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Watch, Clock, Part Mfg",
                "code" => "13345181",
            ],
            [
                "company_alias" => "nic",
                "value" => "Prerecorded Compact Disc, Tape, Record",
                "code" => "13346121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp, Electronic Prod Mfg",
                "code" => "13349991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electric Lamp Bulb, Part Mfg",
                "code" => "13351101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Lighting Fixture Mfg",
                "code" => "13351291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electric Lighting Equip Mfg",
                "code" => "13351991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household Vacuum Cleaner Mfg",
                "code" => "13352121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pen, Mechanical Pencil Mfg",
                "code" => "13399411",
            ],
            [
                "company_alias" => "nic",
                "value" => "Carbon Paper, Inked Ribbon Mfg",
                "code" => "13399441",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sign Mfg",
                "code" => "13399501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Musical Inst Mfg",
                "code" => "13399921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Misc Mfg",
                "code" => "13399993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motor Vehcile Merchant Wholesalers",
                "code" => "14231101",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Parts Merchant Wholesalers",
                "code" => "14231201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Furniture Merchant Wholesalers",
                "code" => "14232101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Brick, Stone, Cons Merchant Wholesalers",
                "code" => "14233201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Lumber, Cons Materials Merchant Wholesalers",
                "code" => "14233991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Office Equip Merchant Wholesalers",
                "code" => "14234201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp, Peripheral Equip, Software Merchant Wholesalers",
                "code" => "14234301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Comm Equip Merchant Wholesalers",
                "code" => "14234401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Medical, Dental, Hospital Equip Merchant Wholesalers",
                "code" => "14234501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ophthalmic Goods Merchant Wholesalers",
                "code" => "14234601",
            ],
            [
                "company_alias" => "nic",
                "value" => "Metal, Mineral Merchant Wholesalers",
                "code" => "14235991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electrical, Electronic Goods Merchant Wholesalers",
                "code" => "14236991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hardware Merchant Wholesalers",
                "code" => "14237101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial Supplies Merchant Wholesalers",
                "code" => "14238401",
            ],
            [
                "company_alias" => "nic",
                "value" => "M/c, Equip, Supplies Merchant Wholesalers",
                "code" => "14238991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Toy, Hobby Goods, Supplies Merchant Wholesalers",
                "code" => "14239201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Jewelry, Watch, Precious Stone/Metal Merchant Wholesalers",
                "code" => "14239401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Merchant Wholesalers, Durable Goods",
                "code" => "14239991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sty, Office Supplies Merchant Wholesalers",
                "code" => "14241201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial, Personal Service Paper Merchant Wholesalers",
                "code" => "14241301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paper, Paper Prod Merchant Wholesalers",
                "code" => "14241991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Drugs, Druggists Sundries Merchant Wholesalers",
                "code" => "14242101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Piece Dry Goods Merchant Wholesalers",
                "code" => "14243101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Footwear Merchant Wholesalers",
                "code" => "14243401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Poultry, Poultry Prod Merchant Wholesalers",
                "code" => "14244401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Confectionery Merchant Wholesalers",
                "code" => "14244501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fish, Seafood Merchant Wholesalers",
                "code" => "14244601",
            ],
            [
                "company_alias" => "nic",
                "value" => "Meat, Meat Prod Merchant Wholesalers",
                "code" => "14244701",
            ],
            [
                "company_alias" => "nic",
                "value" => "Grocery Prod Wholesalers",
                "code" => "14244991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Livestock Merchant Wholesalers",
                "code" => "14245201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Farm Prod Raw Material Merchant Wholesalers",
                "code" => "14245901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Chemical Prods Merchant Wholesalers",
                "code" => "14246991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petro Bulk Stations, Terminals",
                "code" => "14247101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petro, Petro Prods Merchant Wholesalers",
                "code" => "14247201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Beer, Wine, Alcoholic Beverage Merchant Wholesalers",
                "code" => "14248991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Book, Periodical, Newspaper Merchant Wholesalers",
                "code" => "14249201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paint, Varnish, Supplies Merchant Wholesalers",
                "code" => "14249501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Merchant Wholesalers, Nondurable Goods",
                "code" => "14249991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Automobile Dealers",
                "code" => "14411991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motorcycle Dealers",
                "code" => "14412211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Boat Dealers",
                "code" => "14412221",
            ],
            [
                "company_alias" => "nic",
                "value" => "Auto Parts, Accessories Stores",
                "code" => "14413101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tire Dealers",
                "code" => "14413201",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV, Parts Dealers",
                "code" => "14419991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Furniture Stores",
                "code" => "14421101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Home Furnishings Stores",
                "code" => "14422992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household Appliance Stores",
                "code" => "14431111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Radio, TV, Other Electronics Stores",
                "code" => "14431121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp, Software Stores",
                "code" => "14431201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Camera, Photographic Supplies Stores",
                "code" => "14431301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electronics, Appliance Stores",
                "code" => "14431991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hardware Stores",
                "code" => "14441301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Bldg Material Dealers",
                "code" => "14441901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nursery, Garden, Farm Supply Stores",
                "code" => "14442201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bldg Material, Garden Equip, Supplies Dealers",
                "code" => "14449991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Supermarkets",
                "code" => "14451101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Convenience Stores",
                "code" => "14451201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Grocery Stores",
                "code" => "14451991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Meat Markets",
                "code" => "14452101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fish, Seafood Markets",
                "code" => "14452201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fruit, Veg Markets",
                "code" => "14452301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Baked Goods Stores",
                "code" => "14452911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Confectionery, Nut Stores",
                "code" => "14452921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Splty Food Stores",
                "code" => "14452993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Beer, Wine, Liquor Stores",
                "code" => "14453101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Food, Beverage Stores",
                "code" => "14459991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pharmacies, Drug Stores",
                "code" => "14461101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cosmetics, Perfume Stores",
                "code" => "14461201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Optical Goods Stores",
                "code" => "14461301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Food (Health] Supplement Stores",
                "code" => "14461911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Health, Personal Care Stores",
                "code" => "14461992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Petrol, Diesel, Gasoline Stations",
                "code" => "14471992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mens Clothing Stores",
                "code" => "14481101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Womens Clothing Stores",
                "code" => "14481201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Childrens, Infants Clothing Stores",
                "code" => "14481301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Clothing Stores",
                "code" => "14481901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Shoe Stores",
                "code" => "14482101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Jewelry Stores",
                "code" => "14483101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Luggage, Leather Goods Stores",
                "code" => "14483201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Clothing, Clothing Accessories Stores",
                "code" => "14489991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hobby, Toy, Game Stores",
                "code" => "14511201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Musical Inst, Supplies Stores",
                "code" => "14511401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sport Goods, Hobby, Musical Inst Stores",
                "code" => "14511991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Book Stores",
                "code" => "14512111",
            ],
            [
                "company_alias" => "nic",
                "value" => "News Dealers, Newsstands",
                "code" => "14512121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Department Stores",
                "code" => "14521191",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other General Merchandise Stores",
                "code" => "14529901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Florists",
                "code" => "14531101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Office Supplies, Sty Stores",
                "code" => "14532101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Gift, Novelty, Souvenir Stores",
                "code" => "14532201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Art Dealers",
                "code" => "14539201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mfd Mobile Home Dealers",
                "code" => "14539301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Misc Store Retailers",
                "code" => "14539992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electronic Auctions",
                "code" => "14541121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mail-Order Houses",
                "code" => "14541131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Vending Machine Operators",
                "code" => "14542101",
            ],
            [
                "company_alias" => "nic",
                "value" => "LPG (Bottled Gas] Dealers",
                "code" => "14543121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Direct Selling Establishments",
                "code" => "14543901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Scheduled Air Transp",
                "code" => "14811191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nonscheduled Air Transp",
                "code" => "14812191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rail Transp",
                "code" => "14821191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Deep Sea Freight Transp",
                "code" => "14831111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Deep Sea Passenger Transp",
                "code" => "14831121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Coastal, Lakes Freight Transp",
                "code" => "14831131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Coastal, Lakes Passenger Transp",
                "code" => "14831141",
            ],
            [
                "company_alias" => "nic",
                "value" => "Inland Water Freight Transp",
                "code" => "14832111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Inland Water Passenger Transp",
                "code" => "14832121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Water Transp",
                "code" => "14839991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Truck Transp",
                "code" => "14849991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Bus, Other MV Transit Systems",
                "code" => "14851131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Taxi Service",
                "code" => "14853101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Charter Bus Industry",
                "code" => "14855101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Transit, Gr. Passenger Transp",
                "code" => "14859992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Arts, Spectator, Sports Industries",
                "code" => "17119991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Museums",
                "code" => "17121101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Historical Sites",
                "code" => "17121201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Zoos, Botanical Gardens",
                "code" => "17121301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Amusement, Theme Parks",
                "code" => "17131101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Casinos (except Casino Hotels]",
                "code" => "17132101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Golf Courses, Country Clubs",
                "code" => "17139101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Amusement, Recreation Industries",
                "code" => "17139992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Full-Service Restaurants",
                "code" => "17221101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Limited-Service Restaurants",
                "code" => "17222111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cafeterias",
                "code" => "17222121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Caterers",
                "code" => "17223201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mobile Food Services",
                "code" => "17223301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Food Services, Drinking Places",
                "code" => "17229991",
            ],
            [
                "company_alias" => "nic",
                "value" => "General Auto Repair",
                "code" => "18111111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Car Washes",
                "code" => "18111921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Auto Repair, Maint",
                "code" => "18111991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Consumer Electronics Repair, Maint",
                "code" => "18112111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp, Office Machine Repair, Maint",
                "code" => "18112121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Communication Equip Repair, Maint",
                "code" => "18112131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electronic, Precision Equip Repair, Maint",
                "code" => "18112191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Personal, Household Goods Repair, Maint",
                "code" => "18114992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Repair, Maint",
                "code" => "18119991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Beauty Salons",
                "code" => "18121121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Personal Care Services",
                "code" => "18121993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Funeral Homes, Funeral Services",
                "code" => "18122101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cemeteries, Crematories",
                "code" => "18122201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Drycleaning, Laundry Non Coin Operated",
                "code" => "18123201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Drycleaning, Laundry Services",
                "code" => "18123991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Parking Lots, Garages",
                "code" => "18129301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Personal Services",
                "code" => "18129992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Religious Organizations",
                "code" => "18131101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Voluntary Health Organizations",
                "code" => "18132121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Human Rights Organizations",
                "code" => "18133111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Environment, Wildlife Organizations",
                "code" => "18133121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Business Associations",
                "code" => "18139101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Professional Organizations",
                "code" => "18139201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Labor Unions and Organizations",
                "code" => "18139301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Political Organizations",
                "code" => "18139401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Private Households",
                "code" => "18141101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Executive Offices",
                "code" => "19211101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other General Government Support",
                "code" => "19211901",
            ],
            [
                "company_alias" => "nic",
                "value" => "Courts",
                "code" => "19221101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Police Protection",
                "code" => "19221201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household Cooking Appliance Mfg",
                "code" => "13352211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Major Appliance Mfg",
                "code" => "13352291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household Appliance Mfg",
                "code" => "13352991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Power, Distb, Spl Transformer Mfg",
                "code" => "13353111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motor, Generator Mfg",
                "code" => "13353121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Switchgear, Switchboard Apparatus Mfg",
                "code" => "13353131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Electrical Equip Mfg",
                "code" => "13353191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Battery Mfg",
                "code" => "13359191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fiber Optic Cable Mfg",
                "code" => "13359211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Carbon, Graphite Prod Mfg",
                "code" => "13359911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Automobile Mfg",
                "code" => "13361111",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Mfg",
                "code" => "13361991",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Body Mfg",
                "code" => "13362111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Carburetor, Piston, Piston Ring, Valve Mfg",
                "code" => "13363111",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Electrical, Electronic Equip Mfg",
                "code" => "13363291",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Steering, Susp Components Mfg",
                "code" => "13363301",
            ],
            [
                "company_alias" => "nic",
                "value" => "MV Parts Mfg",
                "code" => "13363992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Aircraft Mfg",
                "code" => "13364111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Aerospace Prod, Parts Mfg",
                "code" => "13364191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Railroad Rolling Stock Mfg",
                "code" => "13365101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ship Bldg, Repairing",
                "code" => "13366111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Boat Bldg",
                "code" => "13366121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motorcycle, Bicycle, Parts Mfg",
                "code" => "13369911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household, Inst Furniture Mfg",
                "code" => "13371291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Household, Kitchen Cabinet Mfg",
                "code" => "13371991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mattress Mfg",
                "code" => "13379101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Furniture, Related Prod Mfg",
                "code" => "13379991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Lab Apparatus, Furniture Mfg",
                "code" => "13391111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Surgical, Medical Inst Mfg",
                "code" => "13391121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Advertising Agencies",
                "code" => "15418101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Public Relations Agencies",
                "code" => "15418201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Display Advertising",
                "code" => "15418501",
            ],
            [
                "company_alias" => "nic",
                "value" => "Advertising, Related Services",
                "code" => "15418991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Photography Studios, Portrait",
                "code" => "15419211",
            ],
            [
                "company_alias" => "nic",
                "value" => "Photographic Services",
                "code" => "15419291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Translation, Interpretation Services",
                "code" => "15419301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Veterinary Services",
                "code" => "15419401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Prof Scientific and Technical Services",
                "code" => "15419993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Employment Placement Agencies",
                "code" => "15613101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Business Service Centers",
                "code" => "15614391",
            ],
            [
                "company_alias" => "nic",
                "value" => "Business Support Services",
                "code" => "15614992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Travel Agencies",
                "code" => "15615101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Tour Operators",
                "code" => "15615201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Investigation Services",
                "code" => "15616111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Security Guards, Patrol Services",
                "code" => "15616121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Security Systems Services",
                "code" => "15616291",
            ],
            [
                "company_alias" => "nic",
                "value" => "Administrative, Support Services",
                "code" => "15619991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hazardous Waste Collection",
                "code" => "15621121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Waste Collection",
                "code" => "15621192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hazardous Waste Treatment, Disposal",
                "code" => "15622111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Waste Treatment, Disposal",
                "code" => "15622192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Elementary, Secondary Schools",
                "code" => "16111101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Junior Colleges",
                "code" => "16112101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Colleges, Universities, Prof Schools",
                "code" => "16113101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Business, Secretarial Schools",
                "code" => "16114101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp Training",
                "code" => "16114201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Flight Training",
                "code" => "16115121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Automobile Driving Schools",
                "code" => "16116921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Schools, Instruction",
                "code" => "16116993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Educational Institutions",
                "code" => "16119991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Physicians",
                "code" => "16211191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Dentists",
                "code" => "16212101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Optometrists",
                "code" => "16213201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Mental Health Practitioners",
                "code" => "16213301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Physio Therapists",
                "code" => "16213401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Podiatrists",
                "code" => "16213911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Other Health Practitioners",
                "code" => "16213993",
            ],
            [
                "company_alias" => "nic",
                "value" => "Kidney Dialysis Centers",
                "code" => "16214921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Outpatient Care Centers",
                "code" => "16214991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Medical Lab",
                "code" => "16215111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Diagnostic Imaging Centers",
                "code" => "16215121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Medical, Diagnostic Lab",
                "code" => "16215191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ambulance Services",
                "code" => "16219101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Blood, Organ Banks",
                "code" => "16219911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Hospitals",
                "code" => "16229991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nursing Care Facilities",
                "code" => "16231101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Homes for the Elderly",
                "code" => "16233121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nursing, Residential Care Facilities",
                "code" => "16239991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Emergency, Other Relief Services",
                "code" => "16242301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Vocational Rehabilitation Services",
                "code" => "16243101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Child Day Care Services",
                "code" => "16244101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Ophthalmic Goods Mfg",
                "code" => "13391151",
            ],
            [
                "company_alias" => "nic",
                "value" => "Dental Lab",
                "code" => "13391161",
            ],
            [
                "company_alias" => "nic",
                "value" => "Jewelry, Silverware Mfg",
                "code" => "13399191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sport, Athletic Goods Mfg",
                "code" => "13399201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Doll, Stuffed Toy Mfg",
                "code" => "13399311",
            ],
            [
                "company_alias" => "nic",
                "value" => "Mgmt Consulting Services",
                "code" => "15416191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Environmental Consulting Services",
                "code" => "15416201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Legal Counsel, Prosecution",
                "code" => "19221301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Fire Protection",
                "code" => "19221601",
            ],
            [
                "company_alias" => "nic",
                "value" => "Justice, Public Order, Safety Activities",
                "code" => "19221991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Space Research, Technology",
                "code" => "19271101",
            ],
            [
                "company_alias" => "nic",
                "value" => "National Security",
                "code" => "19281101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Information Technology",
                "code" => "15112991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Science And Technology",
                "code" => "15417991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Packaging Industry",
                "code" => "15619191",
            ],
            [
                "company_alias" => "nic",
                "value" => NULL,
                "code" => "999999",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pipeline Transp of Crude Oil",
                "code" => "14861101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pipeline Transp of Natural Gas",
                "code" => "14862101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pipeline Transp of Refined Petro",
                "code" => "14869101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Pipeline Transp",
                "code" => "14869992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Air Traffic Control",
                "code" => "14881111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Airport Operations",
                "code" => "14881191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Airport Operations",
                "code" => "14881192",
            ],
            [
                "company_alias" => "nic",
                "value" => "Port, Harbor Operations",
                "code" => "14883101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Marine Cargo Handling",
                "code" => "14883201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Packing, Crating",
                "code" => "14889911",
            ],
            [
                "company_alias" => "nic",
                "value" => "Support - Transp",
                "code" => "14889994",
            ],
            [
                "company_alias" => "nic",
                "value" => "Postal Service",
                "code" => "14911101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Couriers",
                "code" => "14921101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Couriers, Messengers",
                "code" => "14929991",
            ],
            [
                "company_alias" => "nic",
                "value" => "General Warehousing, Storage",
                "code" => "14931101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Refrigerated Warehousing, Storage",
                "code" => "14931201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Newspaper Publishers",
                "code" => "15111101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Periodical Publishers",
                "code" => "15111201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Book Publishers",
                "code" => "15111301",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Publishers",
                "code" => "15111991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Software Publishers",
                "code" => "15112101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motion Picture, Video",
                "code" => "15121101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motion Picture, Video Distb",
                "code" => "15121201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Motion Picture, Video Exhibition",
                "code" => "15121391",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Motion Picture, Video Industries",
                "code" => "15121992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Music Publishers",
                "code" => "15122301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Sound Recording Studios",
                "code" => "15122401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Radio Broadcasting",
                "code" => "15151191",
            ],
            [
                "company_alias" => "nic",
                "value" => "TV Broadcasting",
                "code" => "15151201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Internet Publishing, Broadcasting",
                "code" => "15161101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Wired Telecom Carriers",
                "code" => "15171101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Paging",
                "code" => "15172111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cellular, Other Wireless Telecom",
                "code" => "15172121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Telecom Resellers",
                "code" => "15173101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Satellite Telecom",
                "code" => "15174101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Cable, Other Program Distb",
                "code" => "15175101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Internet Service Providers",
                "code" => "15181111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Web Search Portals",
                "code" => "15181121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Libraries, Archives",
                "code" => "15191201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Information Services",
                "code" => "15191992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Monetary Authorities - Central Bank",
                "code" => "15211101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comm Banking",
                "code" => "15221101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Credit Card Issuing",
                "code" => "15222101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Nondepository Credit Intermediation",
                "code" => "15222991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Invs Banking, Securities Dealing",
                "code" => "15231101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Securities Brokerage",
                "code" => "15231201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Commodity Contracts Brokerage",
                "code" => "15231401",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Fin Invs Activities",
                "code" => "15239991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Direct Life Insurance Carriers",
                "code" => "15241131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Reinsurance Carriers",
                "code" => "15241301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Insurance Carriers",
                "code" => "15241991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Insurance Agencies, Brokerages",
                "code" => "15242101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Claims Adjusting",
                "code" => "15242911",
            ],
            [
                "company_alias" => "nic",
                "value" => "TPA of Insurance, Pension Funds",
                "code" => "15242921",
            ],
            [
                "company_alias" => "nic",
                "value" => "Agents, Brokers etc",
                "code" => "15242991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Pension Funds",
                "code" => "15251101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Insurance, Employee Benefit Funds",
                "code" => "15251991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Activities Related to Real Estate",
                "code" => "15313991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Real Estate",
                "code" => "15319991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Passenger Car Rental",
                "code" => "15321111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Passenger Car Leasing",
                "code" => "15321121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Consumer Goods Rental",
                "code" => "15322992",
            ],
            [
                "company_alias" => "nic",
                "value" => "General Rental Centers",
                "code" => "15323101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Rental, Leasing Services",
                "code" => "15329991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Real Estate, Rental, Leasing",
                "code" => "15399991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Lawyers",
                "code" => "15411101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Offices of Notaries",
                "code" => "15411201",
            ],
            [
                "company_alias" => "nic",
                "value" => "All Other Legal Services",
                "code" => "15411991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Accounting, Tax, Payroll Services",
                "code" => "15412191",
            ],
            [
                "company_alias" => "nic",
                "value" => "Architectural Services",
                "code" => "15413101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Engg Services",
                "code" => "15413301",
            ],
            [
                "company_alias" => "nic",
                "value" => "Drafting Services",
                "code" => "15413401",
            ],
            [
                "company_alias" => "nic",
                "value" => "Testing Lab",
                "code" => "15413801",
            ],
            [
                "company_alias" => "nic",
                "value" => "Architectural, Engg, Related Services",
                "code" => "15413991",
            ],
            [
                "company_alias" => "nic",
                "value" => "Interior Design Services",
                "code" => "15414101",
            ],
            [
                "company_alias" => "nic",
                "value" => "Industrial Design Services",
                "code" => "15414201",
            ],
            [
                "company_alias" => "nic",
                "value" => "Splized Design Services",
                "code" => "15414992",
            ],
            [
                "company_alias" => "nic",
                "value" => "Custom Comp Prog Services",
                "code" => "15415111",
            ],
            [
                "company_alias" => "nic",
                "value" => "Comp Facilities Mgmt Services",
                "code" => "15415131",
            ],
            [
                "company_alias" => "nic",
                "value" => "Other Comp Related Services",
                "code" => "15415192",
            ],
            [
                "company_alias" => "nic",
                "value" => "H R, Exec Search Consulting Services",
                "code" => "15416121",
            ],
            [
                "company_alias" => "nic",
                "value" => "Marketing Consulting Services",
                "code" => "15416131",
            ],
        ]);
    }
}
