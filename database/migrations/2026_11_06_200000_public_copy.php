<?php
// Public text rewritten to say why rider registration matters, how it works and who it is for, with no line about
// where the system is made. Each value changes only while it still holds the old default, so text an admin has
// edited is kept. Adds the "why it matters" section (settings home_why_* and content group home_why).
return [
    'description' => 'Public copy rewrite and the why-it-matters home section',
    'up' => function (mysqli $db) {
        $settings = array (
  0 => 
  array (
    0 => 'site_tagline',
    1 => 'Rider permits, fee collection and compliance for boda-boda stages',
    2 => 'Rider registration, permit fees and roadside checks for boda-boda stages',
  ),
  1 => 
  array (
    0 => 'seo_default_title',
    1 => 'BodaERP: boda-boda rider registration and permit fees for Ugandan cities',
    2 => 'BodaERP: register boda-boda riders, collect permit fees, check permits at the roadside',
  ),
  2 => 
  array (
    0 => 'seo_default_description',
    1 => 'BodaERP helps city councils and stage chairpersons in Uganda register boda-boda riders, collect annual permit fees by cash or mobile money, issue QR-coded ID cards and track compliance.',
    2 => 'BodaERP registers boda-boda riders at their stage, collects permit fees by cash or mobile money with a receipt for every payment, issues ID cards with a QR code and lets anyone check a permit in seconds.',
  ),
  3 => 
  array (
    0 => 'home_hero_title',
    1 => 'Every boda-boda rider registered, paid up and verifiable by QR code',
    2 => 'Boda-boda rider registration, permit fees and roadside permit checks for city councils',
  ),
  4 => 
  array (
    0 => 'home_features_title',
    1 => 'What each part of BodaERP does',
    2 => 'What you can do with it',
  ),
  5 => 
  array (
    0 => 'home_features_text',
    1 => 'One system shared by stage chairpersons, city councils, riders and government, each seeing only what they are responsible for.',
    2 => 'The tools each part of the permit system needs, from registering a rider at the stage to the council\'s monthly report.',
  ),
  6 => 
  array (
    0 => 'home_cta_title',
    1 => 'Bringing BodaERP to your city council?',
    2 => 'Ready to register the riders in your city?',
  ),
  7 => 
  array (
    0 => 'home_cta_text',
    1 => 'Tell us about your city, the number of stages and how fees are collected today. We will set up your council, stages and starter data.',
    2 => 'Tell us how many stages your city has and how permit fees are collected today. We set up the council, its stages and the fee, and show the chairpersons how to register their riders.',
  ),
  8 => 
  array (
    0 => 'home_cta_button',
    1 => 'Talk to the BodaERP team',
    2 => 'Talk to us about your city',
  ),
  9 => 
  array (
    0 => 'home_steps_title',
    1 => 'How BodaERP works',
    2 => 'How it works',
  ),
  10 => 
  array (
    0 => 'home_steps_text',
    1 => 'From setting up a city to a roadside permit check, in four steps.',
    2 => 'Four steps take a rider from joining a stage to a permit anyone can check on the road.',
  ),
  11 => 
  array (
    0 => 'home_roles_title',
    1 => 'Who uses BodaERP',
    2 => 'Who it is for, and why them',
  ),
  12 => 
  array (
    0 => 'home_roles_text',
    1 => 'Everyone signs in to a portal built for their job and sees only the records they are responsible for.',
    2 => 'Four groups share one record of every rider. Each has a reason to keep it right, and each sees only what their work needs.',
  ),
  13 => 
  array (
    0 => 'home_verify_text',
    1 => 'Type the permit number printed on the rider\'s ID card to see whether the permit is valid. Only the status and a partly hidden name are shown.',
    2 => 'Before a trip or at a checkpoint, type the permit number printed on the rider\'s ID card. You see at once whether the permit is valid. Only the status and a partly hidden name are shown, to protect the rider.',
  ),
  14 => 
  array (
    0 => 'home_faq_title',
    1 => 'Common questions',
    2 => 'Questions people ask',
  ),
  15 => 
  array (
    0 => 'footer_photo_credit',
    1 => 'Photos of riders and Lira City by Wikimedia Commons contributors.',
    2 => 'Photos by Wikimedia Commons contributors.',
  ),
);
        $s = $db->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = ? AND `value` = ?");
        foreach ($settings as [$key, $old, $new]) { $s->bind_param('sss', $new, $key, $old); $s->execute(); }
        $s->close();

        $newSettings = array (
  array (
    0 => 'home_why_image',
    1 => '/assets/images/home/motorcycle-centre.webp',
    2 => 'image',
    3 => 'home',
    4 => 'Background photo behind the "why it matters" band',
    5 => 0,
    6 => 29,
  ),
  0 => 
  array (
    0 => 'home_why_title',
    1 => 'Why it matters',
    2 => 'text',
    3 => 'home',
    4 => 'Heading of the "why it matters" section',
    5 => 0,
    6 => 27,
  ),
  1 => 
  array (
    0 => 'home_why_text',
    1 => 'Boda-bodas are how many people get to work, to school, to market and to hospital. Knowing who is riding, and whether they hold a valid permit, protects everyone on the road.',
    2 => 'textarea',
    3 => 'home',
    4 => 'Text under the "why it matters" heading',
    5 => 0,
    6 => 28,
  ),
);
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        foreach ($newSettings as [$key, $value, $type, $cat, $desc, $flag, $sort]) { $s->bind_param('sssssii', $key, $value, $type, $cat, $desc, $flag, $sort); $s->execute(); }
        $s->close();

        // group, old title, old body, new title, new body, [button label, button link] or null
        $items = array (
  0 => 
  array (
    0 => 'home_slide',
    1 => 'Every boda-boda rider registered at their own stage',
    2 => 'Stage chairpersons record each rider\'s NIN, photo and number plate once. The city council sees the same record the same day.',
    3 => 'Every rider registered at the stage they work from',
    4 => 'A rider with a name, a photo, a number plate and a stage on record can be traced, trusted by passengers and reached by the council. Chairpersons register riders where they already work.',
    5 => NULL,
  ),
  1 => 
  array (
    0 => 'home_slide',
    1 => 'Check any rider\'s permit at the roadside',
    2 => 'Scan the QR code on the ID card with a phone camera, or type the permit number, to see whether the permit is valid, expired or pending.',
    3 => 'Check a permit in seconds, at the roadside',
    4 => 'Scan the QR code on the rider\'s ID card with any phone camera. The page shows straight away whether the permit is valid, expired or pending, with no paperwork and no phone calls.',
    5 => NULL,
  ),
  2 => 
  array (
    0 => 'home_slide',
    1 => 'Permit fees collected, receipted and accounted for',
    2 => 'Cash at the stage or mobile money from the rider\'s phone. Every payment gets a receipt number and appears in the council\'s revenue report.',
    3 => 'Permit fees that reach the council, with a receipt',
    4 => 'Cash paid at the stage and mobile money paid from the rider\'s phone both get a receipt number and land in the council\'s revenue report, so no payment goes unrecorded.',
    5 => NULL,
  ),
  3 => 
  array (
    0 => 'home_slide',
    1 => 'Built in Lira for city councils across Uganda',
    2 => 'Kakebe Technologies runs BodaERP from Lira City. Each council gets its own portal, stages and permit fee, set up together with its staff.',
    3 => 'Riders who can prove their permit keep working',
    4 => 'A valid permit on the ID card or the rider\'s phone settles a checkpoint stop at once. Riders see their renewal date, pay on time and keep every receipt.',
    5 => 
    array (
      0 => 'Answers for riders',
      1 => '/faq.php',
    ),
  ),
  4 => 
  array (
    0 => 'home_step',
    1 => 'The council sets up the city',
    2 => 'BodaERP adds the city with its permit fee and its stages. The city council gets a portal for riders, payments and reports.',
    3 => 'The council sets the rules',
    4 => 'The city council enters its stages, the yearly permit fee and the grace period. Every chairperson and officer then works from the same rules.',
    5 => NULL,
  ),
  5 => 
  array (
    0 => 'home_step',
    1 => 'Chairpersons register riders',
    2 => 'At the stage, the chairperson records each rider\'s details, photo and motorcycle plate, and collects the permit fee in cash or by mobile money.',
    3 => 'Chairpersons register riders at the stage',
    4 => 'The chairperson knows every rider at the stage, so they record each rider\'s NIN, photo and plate, and collect the fee in cash or by mobile money.',
    5 => NULL,
  ),
  6 => 
  array (
    0 => 'home_step',
    1 => 'Riders get an ID card and a portal',
    2 => 'Every rider receives a printable ID card with a QR code. Riders with an email address also get a portal for their permit, receipts and renewals.',
    3 => 'Each rider gets an ID card',
    4 => 'The card carries the rider\'s photo, stage and a QR code. Riders with an email address or phone number also get a portal for receipts and renewals.',
    5 => NULL,
  ),
  7 => 
  array (
    0 => 'home_step',
    1 => 'Officers check permits at the roadside',
    2 => 'Scanning the QR code, or typing the permit number on this website, shows whether the permit is valid, expired or pending.',
    3 => 'Anyone can check a permit',
    4 => 'Officers and passengers scan the QR code or type the permit number, and see at once whether the permit is valid, expired or pending.',
    5 => NULL,
  ),
  8 => 
  array (
    0 => 'home_role',
    1 => 'City councils',
    2 => 'See every stage, rider and payment in the city, follow up defaulters, record enforcement actions and set the permit fee.',
    3 => 'City councils',
    4 => 'They set the permit fee and answer for road safety in the city. They see every stage, rider and shilling collected, follow up defaulters and plan enforcement from real figures.',
    5 => NULL,
  ),
  9 => 
  array (
    0 => 'home_role',
    1 => 'Stage chairpersons',
    2 => 'Register riders, collect and receipt permit fees, print ID cards and see who is about to expire.',
    3 => 'Stage chairpersons',
    4 => 'They already know every rider at their stage by name, which makes them the right people to register riders, collect fees and hand out ID cards.',
    5 => NULL,
  ),
  10 => 
  array (
    0 => 'home_role',
    1 => 'Riders',
    2 => 'See their permit status, download receipts, renew by mobile money and keep their details up to date.',
    3 => 'Riders',
    4 => 'Their income depends on staying on the road. A permit they can show on a card or a phone, with receipts to prove payment, keeps them working.',
    5 => NULL,
  ),
  11 => 
  array (
    0 => 'home_role',
    1 => 'Government',
    2 => 'A read-only national view of registration, compliance, revenue and enforcement across participating cities.',
    3 => 'Government',
    4 => 'National agencies need the full picture without handling local records. They get a read-only view of registration, compliance and revenue across cities.',
    5 => NULL,
  ),
  12 => 
  array (
    0 => 'home_feature',
    1 => 'Rider registration at the stage',
    2 => 'Chairpersons register riders with their NIN, photo, motorcycle plate and next of kin. Duplicate NINs are refused.',
    3 => 'Register riders once, at the stage',
    4 => 'Name, NIN, photo, plate and next of kin, recorded by the chairperson. A NIN or plate that is already registered is caught before it is saved twice.',
    5 => NULL,
  ),
  13 => 
  array (
    0 => 'home_feature',
    1 => 'Printable ID cards with QR codes',
    2 => 'Each rider gets a card showing their photo, stage and permit number. The QR code opens their live permit status.',
    3 => 'ID cards that prove themselves',
    4 => 'Each card shows the rider\'s photo, stage and permit number. Its QR code opens the live permit status, so an expired or copied card is spotted at once.',
    5 => NULL,
  ),
  14 => 
  array (
    0 => 'home_feature',
    1 => 'Cash and mobile-money fees',
    2 => 'Chairpersons record cash payments; riders can pay renewals by mobile money. Every payment gets a receipt number.',
    3 => 'Cash or mobile money, always receipted',
    4 => 'Chairpersons record cash at the stage and hand it over to the council on record. Riders can renew from their phone. Every payment has a receipt number.',
    5 => NULL,
  ),
  15 => 
  array (
    0 => 'home_feature',
    1 => 'Roadside verification',
    2 => 'Enforcement officers scan a card with any phone camera to see whether the permit is valid, expired or pending.',
    3 => 'Roadside checks on any phone',
    4 => 'Officers scan a card with the phone camera and can note what they did. The council sees every check, where it happened and the outcome.',
    5 => NULL,
  ),
  16 => 
  array (
    0 => 'home_feature',
    1 => 'Renewal reminders',
    2 => 'Riders are reminded in their portal before their permit expires, and chairpersons see who is about to lapse.',
    3 => 'Reminders before a permit lapses',
    4 => 'Riders get a text message and a portal reminder before their permit expires. Chairpersons see who is due this month.',
    5 => NULL,
  ),
  17 => 
  array (
    0 => 'home_feature',
    1 => 'Revenue and compliance reports',
    2 => 'Councils see collections by stage and month, the agreed revenue split, defaulters and enforcement actions.',
    3 => 'Reports councils can act on',
    4 => 'Collections by stage and month, the agreed revenue split, defaulters and enforcement, ready to print or export.',
    5 => NULL,
  ),
  18 => 
  array (
    0 => 'faq',
    1 => 'Our city council wants to use BodaERP. How do we start?',
    2 => 'Send us a message from the contact page with your city, number of stages and current fee. We set up your council account, stages and revenue split, then train your chairpersons.',
    3 => 'Our city council wants to use BodaERP. How do we start?',
    4 => 'Send a message from the contact page with your city, the number of stages and the current permit fee. The council account, stages and revenue split are set up for you, and your chairpersons are shown how to register riders.',
    5 => NULL,
  ),
);
        $s = $db->prepare("UPDATE `content_items` SET `title` = ?, `body` = ? WHERE `group_code` = ? AND `title` = ? AND `body` = ?");
        $link = $db->prepare("UPDATE `content_items` SET `link_label` = ?, `link_url` = ? WHERE `group_code` = ? AND `title` = ?");
        foreach ($items as [$group, $oldTitle, $oldBody, $newTitle, $newBody, $button]) {
            $s->bind_param('sssss', $newTitle, $newBody, $group, $oldTitle, $oldBody);
            $s->execute();
            if ($button && $s->affected_rows) { [$label, $url] = $button; $link->bind_param('ssss', $label, $url, $group, $newTitle); $link->execute(); }
        }
        $s->close();
        $link->close();

        $newItems = array (
  0 => 
  array (
    0 => 'home_why',
    1 => 'fa-user-shield',
    2 => 'Safer trips for passengers',
    3 => 'A passenger can check a rider before the trip. If something goes wrong, the rider can be traced through their stage and their record.',
    4 => 1,
  ),
  1 => 
  array (
    0 => 'home_why',
    1 => 'fa-coins',
    2 => 'Fees that are accounted for',
    3 => 'Cash collected at a stage is receipted and handed over on record, so the council knows what was paid, by whom and what is still owed.',
    4 => 2,
  ),
  2 => 
  array (
    0 => 'home_why',
    1 => 'fa-scale-balanced',
    2 => 'Fair checks for riders',
    3 => 'A rider with a valid permit proves it in seconds. A check rests on the record, not on an argument at the roadside.',
    4 => 3,
  ),
  3 => 
  array (
    0 => 'home_why',
    1 => 'fa-chart-line',
    2 => 'Plans based on real numbers',
    3 => 'Councils decide where to enforce, which stages need help and what to charge from live registration and payment figures.',
    4 => 4,
  ),
);
        $s = $db->prepare("INSERT IGNORE INTO `content_items` (`group_code`,`icon`,`title`,`body`,`sort_order`) VALUES (?,?,?,?,?)");
        foreach ($newItems as [$group, $icon, $title, $body, $sort]) { $s->bind_param('ssssi', $group, $icon, $title, $body, $sort); $s->execute(); }
        $s->close();

        // The short tagline was only shown beside the logo and in the hero badge, both removed.
        $db->query("DELETE FROM `settings` WHERE `key` = 'site_tagline_short'");

        // The About page changes only while its summary is still the original one (an untouched page).
        $s = $db->prepare("UPDATE `pages` SET `summary` = ?, `body` = ?, `meta_description` = ? WHERE `slug` = 'about' AND `summary` = ?");
        $summary = 'What BodaERP is for, how it works and who uses it.';
        $body = '<p>BodaERP keeps one record of every boda-boda rider in a city: who they are, which stage they work from, whether their permit is paid and when it runs out.</p>
<h2>Why it matters</h2>
<p>When riders and permit fees are tracked on paper at each stage, a council cannot see who has paid, an officer cannot check a permit at the roadside, and a rider has no easy way to prove they are legal. Passengers have no way to know who is carrying them.</p>
<h2>How it works</h2>
<ul><li><strong>The council</strong> sets its stages, the yearly permit fee and the grace period.</li>
<li><strong>Stage chairpersons</strong> register the riders at their stage, record payments and hand out ID cards.</li>
<li><strong>Riders</strong> see their permit, download receipts and renew by mobile money.</li>
<li><strong>Anyone</strong> can scan the QR code on an ID card, or type the permit number on this website, to see whether the permit is valid.</li></ul>
<h2>Who it is for</h2>
<p>City councils, because they set the fee and answer for road safety. Chairpersons, because they already know their riders. Riders, because a provable permit keeps them working. Government, because national planning needs the same figures from every city.</p>';
        $meta = 'Why boda-boda riders need to be registered, how BodaERP registers riders and collects permit fees, and who uses it.';
        $oldSummary = 'Who builds BodaERP and what it is for.';
        $s->bind_param('ssss', $summary, $body, $meta, $oldSummary);
        $s->execute();
        $s->close();
    },
];
