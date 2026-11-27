<?php

/*
 * The demo catalogue: categories, and the products in each one.
 *
 * Prices are integer cents. A stock of 0 on a product that has variants (see
 * VariantSeeder) is correct: its stock is the total of its variants. A few
 * products are deliberately low or out of stock, so the admin dashboard has a
 * low-stock list and the shop shows a sold-out state.
 *
 * Images: put a photo named after the product slug in database/seeders/images
 * (for example `wireless-headphones.jpg`). ProductSeeder copies it into
 * storage. A product without a file simply has no photo.
 */

return [
    'electronics' => [
        'name' => 'Electronics',
        'products' => [
            ['name' => 'Wireless Headphones', 'price_cents' => 12999, 'stock' => 34, 'description' => 'Over-ear Bluetooth headphones with up to 30 hours of battery life and soft memory-foam cushions. They fold flat into the included travel case.'],
            ['name' => 'Bluetooth Speaker', 'price_cents' => 5999, 'stock' => 58, 'description' => 'A pocket-sized speaker with surprisingly full sound and a splash-proof body. Pair two for stereo, or clip it to a bag and take it outside.'],
            ['name' => 'Smartwatch', 'price_cents' => 14999, 'stock' => 21, 'description' => 'Tracks steps, sleep and heart rate on a bright always-on screen. Notifications from your phone, and a battery that lasts about a week.'],
            ['name' => 'Laptop Stand', 'price_cents' => 3499, 'stock' => 76, 'description' => 'A sturdy aluminium stand that lifts your screen to eye level and lets your laptop run cooler. Adjusts to six heights and folds flat for travel.'],
            ['name' => 'Mechanical Keyboard', 'price_cents' => 8999, 'stock' => 3, 'description' => 'Compact 75% layout with tactile switches, a solid metal plate and PBT keycaps. Connects over USB-C or Bluetooth to up to three devices.'],
            ['name' => 'USB-C Hub', 'price_cents' => 4499, 'stock' => 45, 'description' => 'Seven ports in one slim adapter: HDMI 4K, two USB-A, an SD card reader and 100 W power pass-through. Turns a thin laptop into a full desk setup.'],
            ['name' => 'Wireless Mouse', 'price_cents' => 2999, 'stock' => 90, 'description' => 'A quiet, comfortable mouse with a shaped grip and a rechargeable battery. A month of use on a single charge.'],
            ['name' => 'Portable Charger', 'price_cents' => 3999, 'stock' => 62, 'description' => 'A 20,000 mAh power bank that charges a phone about four times. Fast charging over USB-C, with a small display that shows what is left.'],
            ['name' => 'Noise Cancelling Earbuds', 'price_cents' => 17999, 'stock' => 0, 'description' => 'True wireless earbuds with active noise cancelling and a transparency mode for when you need to hear the street. Six hours per charge, 24 with the case.'],
            ['name' => 'HD Webcam', 'price_cents' => 6999, 'stock' => 28, 'description' => '1080p video with autofocus, a built-in privacy shutter and two microphones that pick up your voice, not your keyboard. Works with any video call app.'],
        ],
    ],

    'clothing' => [
        'name' => 'Clothing',
        'products' => [
            ['name' => 'Cotton T-Shirt', 'price_cents' => 2400, 'stock' => 0, 'description' => 'A soft, mid-weight tee in 100% combed cotton with a relaxed everyday fit. It keeps its shape and its colour wash after wash.'],
            ['name' => 'Denim Jacket', 'price_cents' => 8900, 'stock' => 0, 'description' => 'A classic trucker jacket in sturdy rigid denim that softens as you wear it. Metal buttons, two chest pockets and a slightly cropped cut.'],
            ['name' => 'Running Shoes', 'price_cents' => 9500, 'stock' => 0, 'description' => 'Lightweight trainers with a breathable mesh upper and a cushioned, springy sole. Built for daily runs and long days on your feet.'],
            ['name' => 'Wool Beanie', 'price_cents' => 2200, 'stock' => 0, 'description' => 'A warm ribbed beanie in soft merino blend that does not itch. Stretchy enough to fit most heads and thin enough to sit under a hood.'],
            ['name' => 'Canvas Tote Bag', 'price_cents' => 1800, 'stock' => 70, 'description' => 'A roomy tote in heavy cotton canvas with reinforced handles and an inside pocket. Holds groceries, a laptop or a day at the beach.'],
            ['name' => 'Slim Fit Jeans', 'price_cents' => 5900, 'stock' => 0, 'description' => 'Slim through the leg with a little stretch for comfort. A mid-rise waist and a clean dark wash that goes with almost anything.'],
            ['name' => 'Hooded Sweatshirt', 'price_cents' => 5500, 'stock' => 0, 'description' => 'A heavyweight fleece hoodie with a double-lined hood and a kangaroo pocket. Brushed on the inside, so it feels warm from the first minute.'],
            ['name' => 'Leather Belt', 'price_cents' => 3200, 'stock' => 4, 'description' => 'A full-grain leather belt with a brushed metal buckle. Ages well, and the five-hole adjustment fits a range of waist sizes.'],
            ['name' => 'Ankle Socks (3-Pack)', 'price_cents' => 1200, 'stock' => 120, 'description' => 'Three pairs of cushioned cotton-blend ankle socks with a reinforced heel and toe. Breathable enough for all-day wear.'],
            ['name' => 'Baseball Cap', 'price_cents' => 1900, 'stock' => 0, 'description' => 'A six-panel cap in washed cotton twill with a curved brim and an adjustable strap. Looks better the more worn in it gets.'],
        ],
    ],

    'home-kitchen' => [
        'name' => 'Home & Kitchen',
        'products' => [
            ['name' => 'Ceramic Coffee Mug', 'price_cents' => 1400, 'stock' => 85, 'description' => 'A hand-glazed stoneware mug that holds 350 ml and keeps drinks warm longer than thin cups. Microwave and dishwasher safe.'],
            ['name' => 'Stainless Steel Water Bottle', 'price_cents' => 2800, 'stock' => 64, 'description' => 'Double-wall insulated, so cold drinks stay cold for 24 hours and hot ones stay hot for 12. Leak-proof lid, and it fits most cup holders.'],
            ['name' => 'Cast Iron Skillet', 'price_cents' => 3900, 'stock' => 26, 'description' => 'A pre-seasoned 26 cm skillet that goes from stovetop to oven to campfire. Gets better with every meal you cook in it.'],
            ['name' => 'Table Lamp', 'price_cents' => 4900, 'stock' => 18, 'description' => 'A warm, dimmable lamp with a linen shade and a solid wooden base. Soft enough for the evening, bright enough to read by.'],
            ['name' => 'Throw Pillow Cover', 'price_cents' => 1600, 'stock' => 52, 'description' => 'A textured cotton-linen cover with a hidden zip, sized for a 45 cm cushion. Washable, so the sofa can look new again.'],
            ['name' => 'Bamboo Cutting Board', 'price_cents' => 2600, 'stock' => 41, 'description' => 'A large, hard-wearing board with a juice groove and a built-in handle. Gentle on knives and easy to keep clean.'],
            ['name' => 'Scented Candle', 'price_cents' => 1800, 'stock' => 5, 'description' => 'A soy wax candle with notes of cedar and warm vanilla, poured by hand into a reusable glass jar. Burns for around 40 hours.'],
            ['name' => 'Indoor Plant Pot', 'price_cents' => 2200, 'stock' => 37, 'description' => 'A matte ceramic pot with a drainage hole and a matching saucer. Fits a plant up to 15 cm wide, and looks good on a shelf or a windowsill.'],
            ['name' => 'Kitchen Knife Set', 'price_cents' => 7900, 'stock' => 14, 'description' => 'Chef, bread, utility and paring knives in high-carbon stainless steel, with a wooden block that keeps the blades sharp and safe.'],
            ['name' => 'Cotton Bath Towel', 'price_cents' => 2400, 'stock' => 60, 'description' => 'A thick, absorbent towel in long-staple cotton that gets softer with every wash. Generous 70 x 140 cm size.'],
        ],
    ],

    'books' => [
        'name' => 'Books',
        'products' => [
            ['name' => 'The Art of Focus', 'price_cents' => 1699, 'stock' => 40, 'description' => 'A practical guide to doing deep work in a distracted world, with short chapters and exercises you can try the same day.'],
            ['name' => 'Modern Web Design', 'price_cents' => 3499, 'stock' => 22, 'description' => 'Layout, typography and colour for the web, explained with plenty of before-and-after examples. Good for developers who want their work to look better.'],
            ['name' => 'A History of Everything', 'price_cents' => 2299, 'stock' => 33, 'description' => 'From the first stars to the first cities in 400 readable pages. A sweeping story told with humour and a lot of curiosity.'],
            ['name' => 'The Midnight Garden', 'price_cents' => 1499, 'stock' => 47, 'description' => 'A quiet, atmospheric novel about a girl who finds a garden that only opens after dark. Perfect for a rainy weekend.'],
            ['name' => 'Cooking for Beginners', 'price_cents' => 2499, 'stock' => 29, 'description' => 'Sixty simple recipes with clear steps and short ingredient lists. Learn the basics once and you will not need a recipe for most weeknight meals.'],
            ['name' => 'Small Habits, Big Change', 'price_cents' => 1899, 'stock' => 55, 'description' => 'A friendly look at how tiny daily changes add up, with a simple system for building habits that last.'],
            ['name' => 'The Silent Ocean', 'price_cents' => 1599, 'stock' => 6, 'description' => 'A research voyage, a missing crew member and a sea that gives nothing away. A slow-burning thriller that is hard to put down.'],
            ['name' => 'Starting a Business 101', 'price_cents' => 2699, 'stock' => 31, 'description' => 'From idea to first customer: validating a product, handling money and avoiding the mistakes most new founders make.'],
            ['name' => 'Poems for Quiet Mornings', 'price_cents' => 1199, 'stock' => 44, 'description' => 'A small collection of short poems about coffee, weather and the first hour of the day. Easy to read in one sitting, easy to reread.'],
            ['name' => 'The Last Expedition', 'price_cents' => 1899, 'stock' => 19, 'description' => 'The true story of a polar expedition that went wrong, told through the diaries of the people who lived it.'],
        ],
    ],

    'toys' => [
        'name' => 'Toys',
        'products' => [
            ['name' => 'Wooden Building Blocks', 'price_cents' => 2900, 'stock' => 48, 'description' => '60 smooth beechwood blocks in different shapes, in a cotton storage bag. Safe, sturdy and good for hours of building.'],
            ['name' => 'Plush Teddy Bear', 'price_cents' => 2200, 'stock' => 56, 'description' => 'A very soft, huggable bear with a stitched smile and a machine-washable coat. Around 35 cm tall.'],
            ['name' => 'Remote Control Car', 'price_cents' => 3900, 'stock' => 23, 'description' => 'A fast off-road car with big grippy tyres and a rechargeable battery. The remote reaches 30 metres, so there is room to race.'],
            ['name' => 'Puzzle Set (500 pcs)', 'price_cents' => 1600, 'stock' => 36, 'description' => 'A 500-piece jigsaw printed on thick, glare-free board. The pieces fit cleanly, and the finished picture looks great on the wall.'],
            ['name' => 'Classic Board Game', 'price_cents' => 2800, 'stock' => 27, 'description' => 'A family strategy game for two to five players that takes about 45 minutes. Easy to learn, with enough depth to play again and again.'],
            ['name' => 'Rubber Duck Bath Toy', 'price_cents' => 899, 'stock' => 110, 'description' => 'A cheerful, squeaky duck that floats upright. Made from BPA-free rubber with a sealed hole, so no water gets inside.'],
            ['name' => 'Building Bricks Set', 'price_cents' => 4500, 'stock' => 2, 'description' => '500 colourful interlocking bricks with a few wheels and windows, plus an idea booklet. Compatible with most major brick brands.'],
            ['name' => 'Toy Dinosaur Figure', 'price_cents' => 1400, 'stock' => 72, 'description' => 'A detailed, hand-painted T. rex with a movable jaw and a sturdy stance. About 20 cm long.'],
            ['name' => 'Kids Art Easel', 'price_cents' => 5900, 'stock' => 12, 'description' => 'A double-sided easel with a chalkboard on one side and a whiteboard on the other. Holds a paper roll, and folds away when playtime is over.'],
            ['name' => 'Spinning Top Set', 'price_cents' => 1100, 'stock' => 65, 'description' => 'Four metal spinning tops with launchers and a small arena. Quick to set up, and good for races between friends.'],
        ],
    ],
];
