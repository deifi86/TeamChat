<?php

namespace App\Services;

class EmojiService
{
    /**
     * Mapping von Shortcodes zu Unicode Emojis
     */
    private array $shortcodes = [
        // Smileys
        ':smile:' => '😊',
        ':grin:' => '😁',
        ':joy:' => '😂',
        ':rofl:' => '🤣',
        ':wink:' => '😉',
        ':blush:' => '😊',
        ':heart_eyes:' => '😍',
        ':kissing:' => '😘',
        ':thinking:' => '🤔',
        ':neutral:' => '😐',
        ':expressionless:' => '😑',
        ':unamused:' => '😒',
        ':rolling_eyes:' => '🙄',
        ':grimacing:' => '😬',
        ':lying:' => '🤥',
        ':relieved:' => '😌',
        ':pensive:' => '😔',
        ':sleepy:' => '😪',
        ':drooling:' => '🤤',
        ':sleeping:' => '😴',
        ':mask:' => '😷',
        ':sick:' => '🤒',
        ':nerd:' => '🤓',
        ':sunglasses:' => '😎',
        ':confused:' => '😕',
        ':worried:' => '😟',
        ':frown:' => '☹️',
        ':open_mouth:' => '😮',
        ':hushed:' => '😯',
        ':astonished:' => '😲',
        ':flushed:' => '😳',
        ':scream:' => '😱',
        ':fearful:' => '😨',
        ':cold_sweat:' => '😰',
        ':cry:' => '😢',
        ':sob:' => '😭',
        ':angry:' => '😠',
        ':rage:' => '😡',
        ':triumph:' => '😤',
        ':skull:' => '💀',
        ':poop:' => '💩',
        ':clown:' => '🤡',
        ':ghost:' => '👻',
        ':alien:' => '👽',
        ':robot:' => '🤖',
        ':cat:' => '😺',
        ':heart:' => '❤️',
        ':orange_heart:' => '🧡',
        ':yellow_heart:' => '💛',
        ':green_heart:' => '💚',
        ':blue_heart:' => '💙',
        ':purple_heart:' => '💜',
        ':black_heart:' => '🖤',
        ':broken_heart:' => '💔',
        ':fire:' => '🔥',
        ':sparkles:' => '✨',
        ':star:' => '⭐',
        ':star2:' => '🌟',
        ':zap:' => '⚡',
        ':boom:' => '💥',
        ':question:' => '❓',
        ':exclamation:' => '❗',

        // Gesten
        ':thumbsup:' => '👍',
        ':thumbsdown:' => '👎',
        ':+1:' => '👍',
        ':-1:' => '👎',
        ':ok_hand:' => '👌',
        ':punch:' => '👊',
        ':fist:' => '✊',
        ':v:' => '✌️',
        ':wave:' => '👋',
        ':hand:' => '✋',
        ':clap:' => '👏',
        ':muscle:' => '💪',
        ':pray:' => '🙏',
        ':point_up:' => '☝️',
        ':point_down:' => '👇',
        ':point_left:' => '👈',
        ':point_right:' => '👉',
        ':middle_finger:' => '🖕',
        ':writing_hand:' => '✍️',

        // Objekte & Symbole
        ':check:' => '✅',
        ':x:' => '❌',
        ':warning:' => '⚠️',
        ':no_entry:' => '⛔',
        ':recycle:' => '♻️',
        ':white_check_mark:' => '✅',
        ':ballot_box_with_check:' => '☑️',
        ':heavy_check_mark:' => '✔️',
        ':clock:' => '🕐',
        ':hourglass:' => '⏳',
        ':watch:' => '⌚',
        ':phone:' => '📱',
        ':computer:' => '💻',
        ':keyboard:' => '⌨️',
        ':mouse:' => '🖱️',
        ':printer:' => '🖨️',
        ':camera:' => '📷',
        ':video:' => '📹',
        ':tv:' => '📺',
        ':radio:' => '📻',
        ':speaker:' => '🔊',
        ':mute:' => '🔇',
        ':bell:' => '🔔',
        ':no_bell:' => '🔕',
        ':microphone:' => '🎤',
        ':headphones:' => '🎧',
        ':cd:' => '💿',
        ':dvd:' => '📀',
        ':battery:' => '🔋',
        ':electric_plug:' => '🔌',
        ':bulb:' => '💡',
        ':flashlight:' => '🔦',
        ':wrench:' => '🔧',
        ':hammer:' => '🔨',
        ':nut_and_bolt:' => '🔩',
        ':gear:' => '⚙️',
        ':link:' => '🔗',
        ':paperclip:' => '📎',
        ':scissors:' => '✂️',
        ':file_folder:' => '📁',
        ':open_file_folder:' => '📂',
        ':page_facing_up:' => '📄',
        ':page_with_curl:' => '📃',
        ':calendar:' => '📅',
        ':clipboard:' => '📋',
        ':pushpin:' => '📌',
        ':straight_ruler:' => '📏',
        ':triangular_ruler:' => '📐',
        ':pencil2:' => '✏️',
        ':memo:' => '📝',
        ':lock:' => '🔒',
        ':unlock:' => '🔓',
        ':key:' => '🔑',
        ':email:' => '📧',
        ':envelope:' => '✉️',
        ':inbox_tray:' => '📥',
        ':outbox_tray:' => '📤',
        ':package:' => '📦',
        ':label:' => '🏷️',
        ':bookmark:' => '🔖',
        ':chart:' => '📊',
        ':chart_with_upwards_trend:' => '📈',
        ':chart_with_downwards_trend:' => '📉',
        ':bar_chart:' => '📊',

        // Essen & Trinken
        ':coffee:' => '☕',
        ':tea:' => '🍵',
        ':beer:' => '🍺',
        ':beers:' => '🍻',
        ':wine:' => '🍷',
        ':cocktail:' => '🍸',
        ':pizza:' => '🍕',
        ':hamburger:' => '🍔',
        ':fries:' => '🍟',
        ':hotdog:' => '🌭',
        ':taco:' => '🌮',
        ':burrito:' => '🌯',
        ':cake:' => '🎂',
        ':cookie:' => '🍪',
        ':chocolate:' => '🍫',
        ':candy:' => '🍬',
        ':apple:' => '🍎',
        ':banana:' => '🍌',
        ':grapes:' => '🍇',
        ':watermelon:' => '🍉',
        ':strawberry:' => '🍓',
        ':lemon:' => '🍋',
        ':orange:' => '🍊',
        ':peach:' => '🍑',
        ':cherries:' => '🍒',
        ':avocado:' => '🥑',
        ':eggplant:' => '🍆',
        ':tomato:' => '🍅',
        ':corn:' => '🌽',
        ':carrot:' => '🥕',
        ':bread:' => '🍞',
        ':egg:' => '🥚',
        ':bacon:' => '🥓',
        ':cheese:' => '🧀',
        ':poultry_leg:' => '🍗',
        ':meat:' => '🥩',
        ':spaghetti:' => '🍝',
        ':sushi:' => '🍣',
        ':ramen:' => '🍜',
        ':ice_cream:' => '🍨',
        ':doughnut:' => '🍩',
        ':popcorn:' => '🍿',
    ];

    /**
     * Konvertiert Shortcodes in Unicode Emojis
     */
    public function convertShortcodes(string $text): string
    {
        foreach ($this->shortcodes as $code => $emoji) {
            $text = str_replace($code, $emoji, $text);
        }

        return $text;
    }

    /**
     * Konvertiert Unicode Emojis zurück zu Shortcodes
     */
    public function convertToShortcodes(string $text): string
    {
        $flipped = array_flip($this->shortcodes);

        foreach ($flipped as $emoji => $code) {
            $text = str_replace($emoji, $code, $text);
        }

        return $text;
    }

    /**
     * Gibt alle verfügbaren Emojis zurück
     */
    public function getAvailableEmojis(): array
    {
        return array_unique(array_values($this->shortcodes));
    }

    /**
     * Gibt alle Shortcodes gruppiert nach Kategorie zurück
     */
    public function getShortcodesByCategory(): array
    {
        return [
            'smileys' => [
                ':smile:', ':grin:', ':joy:', ':wink:', ':heart_eyes:',
                ':thinking:', ':unamused:', ':sunglasses:', ':cry:', ':angry:',
            ],
            'gestures' => [
                ':thumbsup:', ':thumbsdown:', ':ok_hand:', ':clap:', ':wave:',
                ':muscle:', ':pray:', ':v:', ':fist:',
            ],
            'hearts' => [
                ':heart:', ':orange_heart:', ':yellow_heart:', ':green_heart:',
                ':blue_heart:', ':purple_heart:', ':broken_heart:',
            ],
            'symbols' => [
                ':check:', ':x:', ':warning:', ':fire:', ':sparkles:',
                ':star:', ':zap:', ':question:', ':exclamation:',
            ],
            'food' => [
                ':coffee:', ':pizza:', ':hamburger:', ':beer:', ':cake:',
                ':apple:', ':banana:', ':cookie:',
            ],
            'objects' => [
                ':computer:', ':phone:', ':email:', ':lock:', ':key:',
                ':bulb:', ':wrench:', ':gear:',
            ],
        ];
    }

    /**
     * Prüft ob ein String ein gültiges Emoji ist
     */
    public function isValidEmoji(string $emoji): bool
    {
        // Prüfe ob es ein bekannter Shortcode ist
        if (isset($this->shortcodes[$emoji])) {
            return true;
        }

        // Prüfe ob es ein Unicode Emoji ist
        if (in_array($emoji, $this->shortcodes)) {
            return true;
        }

        // Erlaube auch andere Unicode Emojis
        // Regex für Unicode Emoji Range
        $emojiPattern = '/[\x{1F600}-\x{1F64F}' . // Emoticons
                        '\x{1F300}-\x{1F5FF}' .   // Misc Symbols
                        '\x{1F680}-\x{1F6FF}' .   // Transport
                        '\x{1F1E0}-\x{1F1FF}' .   // Flags
                        '\x{2600}-\x{26FF}' .     // Misc symbols
                        '\x{2700}-\x{27BF}' .     // Dingbats
                        '\x{FE00}-\x{FE0F}' .     // Variation Selectors
                        '\x{1F900}-\x{1F9FF}' .   // Supplemental Symbols
                        '\x{1FA00}-\x{1FA6F}' .   // Chess Symbols
                        '\x{1FA70}-\x{1FAFF}' .   // Symbols Extended-A
                        '\x{231A}-\x{231B}' .     // Watch, Hourglass
                        '\x{23E9}-\x{23F3}' .     // Media controls
                        '\x{23F8}-\x{23FA}' .     // More media
                        '\x{25AA}-\x{25AB}' .     // Squares
                        '\x{25B6}\x{25C0}' .      // Triangles
                        '\x{25FB}-\x{25FE}' .     // More squares
                        '\x{2614}-\x{2615}' .     // Umbrella, Hot beverage
                        '\x{2648}-\x{2653}' .     // Zodiac
                        '\x{267F}' .              // Wheelchair
                        '\x{2693}' .              // Anchor
                        '\x{26A1}' .              // High voltage
                        '\x{26AA}-\x{26AB}' .     // Circles
                        '\x{26BD}-\x{26BE}' .     // Soccer, Baseball
                        '\x{26C4}-\x{26C5}' .     // Snowman, Sun
                        '\x{26CE}' .              // Ophiuchus
                        '\x{26D4}' .              // No entry
                        '\x{26EA}' .              // Church
                        '\x{26F2}-\x{26F3}' .     // Fountain, Golf
                        '\x{26F5}' .              // Sailboat
                        '\x{26FA}' .              // Tent
                        '\x{26FD}' .              // Fuel pump
                        '\x{2702}' .              // Scissors
                        '\x{2705}' .              // Check mark
                        '\x{2708}-\x{270D}' .     // Airplane to Writing hand
                        '\x{270F}' .              // Pencil
                        '\x{2712}' .              // Black nib
                        '\x{2714}' .              // Check mark
                        '\x{2716}' .              // X mark
                        '\x{271D}' .              // Latin cross
                        '\x{2721}' .              // Star of David
                        '\x{2728}' .              // Sparkles
                        '\x{2733}-\x{2734}' .     // Eight spoked asterisk
                        '\x{2744}' .              // Snowflake
                        '\x{2747}' .              // Sparkle
                        '\x{274C}' .              // Cross mark
                        '\x{274E}' .              // Cross mark
                        '\x{2753}-\x{2755}' .     // Question marks
                        '\x{2757}' .              // Exclamation
                        '\x{2763}-\x{2764}' .     // Hearts
                        '\x{2795}-\x{2797}' .     // Math symbols
                        '\x{27A1}' .              // Right arrow
                        '\x{27B0}' .              // Curly loop
                        '\x{27BF}' .              // Double curly loop
                        '\x{2934}-\x{2935}' .     // Arrows
                        '\x{2B05}-\x{2B07}' .     // Arrows
                        '\x{2B1B}-\x{2B1C}' .     // Squares
                        '\x{2B50}' .              // Star
                        '\x{2B55}' .              // Circle
                        '\x{3030}' .              // Wavy dash
                        '\x{303D}' .              // Part alternation mark
                        '\x{3297}' .              // Circled Ideograph Congratulation
                        '\x{3299}' .              // Circled Ideograph Secret
                        ']+/u';

        return preg_match($emojiPattern, $emoji) === 1;
    }
}
