<?php

/**
 * @file ***************** *\
 *  ADMIN PAGES        *
\* ***************** .*/

/**
 * @author Martin Scholz
 */
function wisski_textanly_admin_analyse_tagger($op, $ap) {

  switch ($op) {

    case 'edit':

      $preprocessor = $ap->settings;

      $items['preprocessor_infotext'] = [
        '#type' => 'markup',
        '#value' => t('A preprocessor may improve text analysis. The preprocessor can be defined as an external programm. It must read words line by line from stdin and print line by line token, lemma and part-of-speech tags, separated by a tab.'),
      ];
      $items['de'] = [
        '#type' => 'fieldset',
        '#title' => t('Settings for German'),
      ];
      $items['de']['cmd'] = [
        '#type' => 'textfield',
        '#title' => t('Preprocessor command'),
        '#description' => t('with optional arguments. data will be streamed to stdin and read from stdout.'),
        '#default_value' => $preprocessor['de']['cmd'],
      ];
      $items['de']['tokenizes'] = [
        '#type' => 'checkbox',
        '#title' => t('Tagger also tokenizes text.'),
        '#default_value' => $preprocessor['de']['tokenizes'],
      ];
      $items['de']['unknown_lemma'] = [
        '#type' => 'textfield',
        '#title' => t('String that identifies an unknown lemma'),
        '#default_value' => $preprocessor['de']['unknown_lemma'],
      ];
      $items['en'] = [
        '#type' => 'fieldset',
        '#title' => t('Settings for English'),
      ];
      $items['en']['cmd'] = [
        '#type' => 'textfield',
        '#title' => t('Preprocessor command'),
        '#description' => t('with optional arguments. data will be streamed to stdin and read from stdout.'),
        '#default_value' => $preprocessor['en']['cmd'],
      ];
      $items['en']['tokenizes'] = [
        '#type' => 'checkbox',
        '#title' => t('Tagger also tokenizes text.'),
        '#default_value' => $preprocessor['en']['tokenizes'],
      ];
      $items['en']['unknown_lemma'] = [
        '#type' => 'textfield',
        '#title' => t('String that identifies an unknown lemma'),
        '#default_value' => $preprocessor['en']['unknown_lemma'],
      ];
      return $items;

    case 'key_info':
      return [
        'input' => [
          'text',
          'tokens',
        ],
        'output' => [
          'tokens',
          'lemmata',
          'pos',
        ],
      ];

    default:

      return NULL;

  }

}

/**
 * Call an external preprocessor for lemma and part-of-speech.
 *
 * @return token array where tokens are enhanced with keys 'lemma' and 'pos'
 *   where information was available
 *
 * @author Martin Scholz
 */
function wisski_textanly_analyse_tagger($analyser, $text_struct, $ticket) {

  $lang = $text_struct['lang'];
  if (!isset($analyser->settings[$lang]) || !isset($analyser->settings[$lang]['cmd'])) {
    return $text_struct;
  }
  $proc = $analyser->settings[$lang];

  // Build tagger input.
  if ($proc['tokenizes']) {
    // Tagger also tokenizes => just use text.
    $str = $text_struct['text'];
  }
  elseif (!isset($text_struct['tokens']) || empty($text_struct['tokens'])) {
    // Tagger does not tokenize and we don't have tokens => noop.
    return $text_struct;
  }
  else {
    // Build from tokens: one word per line.
    $str = "";
    foreach ($text_struct['tokens'] as $t) {
      $str .= $t[0] . "\n";
    }
    if (trim($str) == '') {
      return $text_struct;
    }
  }

  // Prepare process call.
  $descriptorspec = [
  // STDIN ist eine Pipe, von der das Child liest.
    0 => ["pipe", "r"],
  // STDOUT ist eine Pipe, in die das Child schreibt.
    1 => ["pipe", "w"],
    2 => ["pipe", "a"],
  ];

  // Start process.
  $ph = proc_open($proc['cmd'], $descriptorspec, $pipes);
  if (!is_resource($ph)) {
    wisski_textanly_log($ticket, "Tagger error: Could not call preprocessor.", "err");
    return $text_struct;
  }

  // Send tokens.
  fwrite($pipes[0], $str);
  fflush($pipes[0]);
  fclose($pipes[0]);

  // Get result and finish process.
  $lines = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  if ($err) {
    wisski_textanly_log($ticket, 'Tagger error: ' . $err, 'err');
  }
  proc_close($ph);

  // Parse output.
  $tokens = $proc['tokenizes'] ? [] : $text_struct['tokens'];
  $lemmata = [];
  $pos = [];

  $unknown = $proc['unknown_lemma'];

  $i = 0;
  // Only needed if tokenizes.
  $s = 0;
  $lines = explode("\n", $lines);

  foreach ($lines as $line) {

    $line = rtrim($line);
    if (empty($line)) {
      // Only whitespace, skip.
      continue;
    }

    // Should be array(<token>, <pos>, <lemma>, <something else...>?)
    $line = explode("\t", $line);

    // Token.
    if ($proc['tokenizes']) {
      $s = mb_strpos($str, $line[0], $s);
      if ($s === FALSE) {
        wisski_textanly_log($ticket, 'Tagger error: output token does not match input');
        return $text_struct;
      }
      $e = $s + mb_strlen($line[0]);
      $tokens[$i] = [$line[0], $s, $e];
      $s = $e;
    }

    // Lemma.
    if (isset($line[2]) && $line[2] != $unknown) {
      // This is depricated, remove it eventually.
      $tokens[$i]['lemma'] = $line[2];
      $lemmata[] = $line[2];
    }
    else {
      $lemmata[] = '';
    }

    // Pos.
    if (isset($line[1]) && !empty($line[1])) {
      // This is depricated, remove it eventually.
      $tokens[$i]['pos'] = $line[1];
      $pos[] = $line[1];
    }
    else {
      $pos[] = '';
    }
    $i++;
  }

  $text_struct['tokens'] = $tokens;
  $text_struct['lemmata'] = $lemmata;
  $text_struct['pos'] = $pos;

  return $text_struct;

}
