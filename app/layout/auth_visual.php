<?php
/**
 * Left column of every auth page (sign in, sign up, forgot/reset password, staff sign in):
 * the brand, four interactive mascots, and a short heading. Animated by assets/js/pages/mascots.js.
 *
 * Usage in a page:   $visual_heading = '…'; $visual_text = '…'; require __DIR__ . '/../app/layout/auth_visual.php';
 *                    …and layout_end(['pages/mascots.js']);
 *
 * SVG anatomy (the script relies on these classes/ids):
 *   .m-float  idle bobbing        .m-react  hover / click reactions      .m-lean  leaning toward the form
 *   #<name>-eyes > .eye > .eye-open (white + .pupil) + .eye-closed (the "shut" line)
 *   .m-brows (curious)  .mouth-smile / .mouth-o  .m-cheeks (blush)  g.hand (data-cx/cy = rest spot, data-eye-x/y = eye it covers)
 */
$visual_heading = $visual_heading ?? 'Freshly poured.';
$visual_text = $visual_text ?? 'Order from nine cafés and earn a point for every ringgit.';

const MASCOT_INK = '#3A2418';

/** One eye: white + pupil (with a highlight) that the script moves, and a closed line shown when eyes shut. */
function mascot_eye(float $x, float $y): string
{
	$ink = MASCOT_INK;
	return <<<SVG
	<g class="eye" transform="translate($x $y)">
		<g class="eye-open">
			<ellipse class="eye-white" rx="8.5" ry="9.5" fill="#FFFDF8" stroke="$ink" stroke-width="2.2"/>
			<g class="pupil"><circle r="4.6" fill="$ink"/><circle cx="-1.7" cy="-1.9" r="1.5" fill="#fff"/></g>
		</g>
		<path class="eye-closed" d="M-7.5 1 q7.5 6 15 0" fill="none" stroke="$ink" stroke-width="2.6" stroke-linecap="round" opacity="0"/>
	</g>
SVG;
}

/** Face block: brows, the eyes group (id = "<name>-eyes"), cheeks and two mouths. */
function mascot_face(string $name, float $lx, float $rx, float $ey, float $my): string
{
	$ink = MASCOT_INK;
	$by = $ey - 13;
	$cy = $ey + 10;
	$l = mascot_eye($lx, $ey);
	$r = mascot_eye($rx, $ey);
	$mx = ($lx + $rx) / 2;
	return <<<SVG
	<g class="m-face">
		<g class="m-brows" opacity="0" fill="none" stroke="$ink" stroke-width="2.4" stroke-linecap="round">
			<path d="M{$lx} {$by} m-6 1 q6 -5 12 0"/><path d="M{$rx} {$by} m-6 1 q6 -5 12 0"/>
		</g>
		<g id="{$name}-eyes" class="m-eyes">$l$r</g>
		<g class="m-cheeks" fill="#F4978E" opacity=".55">
			<ellipse cx="{$lx}" cy="{$cy}" rx="5.5" ry="3.5" transform="translate(-9 0)"/>
			<ellipse cx="{$rx}" cy="{$cy}" rx="5.5" ry="3.5" transform="translate(9 0)"/>
		</g>
		<path class="mouth-smile" d="M{$mx} {$my} m-6 0 q6 6 12 0" fill="none" stroke="$ink" stroke-width="2.4" stroke-linecap="round"/>
		<ellipse class="mouth-o" cx="{$mx}" cy="{$my}" rx="3" ry="3.6" fill="$ink" opacity="0"/>
	</g>
SVG;
}

/** Two little paws resting at the sides; each knows which eye it covers. Finger lines make them read as hands. */
function mascot_hands(string $fill, array $left, array $right, array $leftEye, array $rightEye): string
{
	$ink = MASCOT_INK;
	$paw = function (string $side, array $at, array $eye) use ($fill, $ink): string {
		[$x, $y] = $at;
		$f1 = $x - 3.4; $f2 = $x + 3.4; $fy = $y - 7.5;
		return <<<SVG
		<g class="hand hand-$side" data-cx="$x" data-cy="$y" data-eye-x="{$eye[0]}" data-eye-y="{$eye[1]}">
			<ellipse cx="$x" cy="$y" rx="9" ry="8" fill="$fill" stroke="$ink" stroke-width="2.6"/>
			<path d="M$f1 $fy v3.4 M$x {$fy} m0 -0.8 v4 M$f2 $fy v3.4" stroke="$ink" stroke-width="1.6" stroke-linecap="round"/>
		</g>
SVG;
	};
	return '<g class="m-hands">' . $paw('l', $left, $leftEye) . $paw('r', $right, $rightEye) . '</g>';
}

$ink = MASCOT_INK;
$mascots = [
	// Mocha — coffee cup with a caramel sleeve and rising steam
	'coffee' => ['Mocha the coffee cup', <<<SVG
		<g class="m-steam" fill="none" stroke="#F6E7D3" stroke-opacity=".5" stroke-width="4" stroke-linecap="round">
			<path d="M58 42 q-6 -8 0 -15 t0 -15"/><path d="M82 42 q-6 -8 0 -15 t0 -15"/>
		</g>
		<path d="M108 78 q24 2 22 22 q-2 18 -24 20" fill="none" stroke="$ink" stroke-width="12" stroke-linecap="round"/>
		<path d="M108 78 q24 2 22 22 q-2 18 -24 20" fill="none" stroke="#F6E7D3" stroke-width="6" stroke-linecap="round"/>
		<path d="M28 58 h84 l-8 86 q-2 14 -16 14 h-36 q-14 0 -16 -14 Z" fill="#F6E7D3" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<path d="M33 114 H107 L105 135 H35 Z" fill="#D9A066" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<ellipse cx="70" cy="58" rx="42" ry="10" fill="#F6E7D3" stroke="$ink" stroke-width="3"/>
		<ellipse cx="70" cy="58" rx="34" ry="6" fill="#7A4A2E"/>
		<path d="M70 61 c-4 -4 -9 -1 -5 -5 c2 -2 5 0 5 2 c0 -2 3 -4 5 -2 c4 4 -1 1 -5 5Z" fill="#F6E7D3" opacity=".8"/>
SVG
		. mascot_face('coffee', 56, 84, 86, 99) . mascot_hands('#D9A066', [30, 122], [110, 122], [56, 86], [84, 86])],

	// Berry — strawberry cake slice with dripping frosting
	'cake' => ['Berry the cake slice', <<<SVG
		<path d="M26 74 h88 v64 q0 14 -14 14 h-60 q-14 0 -14 -14 Z" fill="#F3D9A6"/>
		<rect x="27" y="120" width="86" height="10" fill="#FFF4E6"/>
		<path d="M26 74 h88 v64 q0 14 -14 14 h-60 q-14 0 -14 -14 Z" fill="none" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<path d="M24 80 q0 -14 14 -14 h64 q14 0 14 14 v6 q-6 8 -12 0 q-6 10 -14 2 q-8 12 -16 0 q-8 10 -16 0 q-6 9 -12 0 q-7 8 -12 0 q-5 6 -10 -2 Z" fill="#F2A7B5" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<g class="m-berry">
			<ellipse cx="70" cy="55" rx="10" ry="11" fill="#E86A6A" stroke="$ink" stroke-width="3"/>
			<path d="M63 45 l7 5 l7 -5 l-3 6 h-8 Z" fill="#8DBF6A" stroke="$ink" stroke-width="2.4" stroke-linejoin="round"/>
			<g fill="#FFE3B3"><circle cx="66" cy="55" r="1.2"/><circle cx="73" cy="58" r="1.2"/><circle cx="71" cy="51" r="1.2"/></g>
		</g>
SVG
		. mascot_face('cake', 56, 84, 101, 113) . mascot_hands('#F2A7B5', [26, 136], [114, 136], [56, 101], [84, 101])],

	// Crumb — a chubby golden croissant
	'croissant' => ['Crumb the croissant', <<<SVG
		<ellipse cx="28" cy="118" rx="19" ry="12" transform="rotate(-35 28 118)" fill="#D9944A" stroke="$ink" stroke-width="3"/>
		<ellipse cx="112" cy="118" rx="19" ry="12" transform="rotate(35 112 118)" fill="#D9944A" stroke="$ink" stroke-width="3"/>
		<ellipse cx="70" cy="106" rx="46" ry="42" fill="#E8B56A" stroke="$ink" stroke-width="3"/>
		<g fill="none" stroke="#C47E3A" stroke-width="3" stroke-linecap="round">
			<path d="M44 76 q-9 30 0 60"/><path d="M96 76 q9 30 0 60"/>
		</g>
		<path d="M52 76 q18 -12 36 0" fill="none" stroke="#FFF4E0" stroke-opacity=".55" stroke-width="4" stroke-linecap="round"/>
SVG
		. mascot_face('croissant', 57, 83, 100, 114) . mascot_hands('#C9853E', [34, 132], [106, 132], [57, 100], [83, 100])],

	// Pearl — taro boba tea with a pink straw and bouncing pearls
	'boba' => ['Pearl the boba tea', <<<SVG
		<rect class="m-straw" x="84" y="6" width="10" height="58" rx="4" transform="rotate(12 89 40)" fill="#F2A7B5" stroke="$ink" stroke-width="3"/>
		<path d="M34 58 h72 l-7 90 q-1 10 -11 10 h-36 q-10 0 -11 -10 Z" fill="#CDB8E6" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<path d="M44 68 l4 64" stroke="#fff" stroke-opacity=".35" stroke-width="4" stroke-linecap="round"/>
		<g class="m-pearls" fill="$ink">
			<circle cx="58" cy="138" r="5"/><circle cx="70" cy="141" r="5"/><circle cx="82" cy="138" r="5"/>
			<circle cx="52" cy="148" r="5"/><circle cx="64" cy="150" r="5"/><circle cx="76" cy="150" r="5"/><circle cx="88" cy="148" r="5"/>
		</g>
		<path d="M32 58 q38 -30 76 0 Z" fill="#EFE6FA" fill-opacity=".85" stroke="$ink" stroke-width="3" stroke-linejoin="round"/>
		<rect x="28" y="54" width="84" height="9" rx="4.5" fill="#EFE6FA" stroke="$ink" stroke-width="3"/>
SVG
		. mascot_face('boba', 57, 83, 88, 101) . mascot_hands('#A98BD0', [32, 116], [108, 116], [57, 88], [83, 88])],
];
?>
<aside class="auth-stage">
	<a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_mark() ?><span><?= APP_NAME ?><small><?= e($visual_tag ?? 'Members') ?></small></span></a>

	<div class="mascots" data-mascots aria-hidden="true">
		<?php foreach ($mascots as $key => [$label, $art]): ?>
			<button type="button" class="mascot mascot-<?= $key ?>" data-mascot="<?= $key ?>" tabindex="-1" title="<?= e($label) ?>">
				<svg viewBox="0 0 140 172" class="mascot-svg" focusable="false">
					<ellipse class="m-shadow" cx="70" cy="165" rx="40" ry="5"/>
					<g class="m-float"><g class="m-react"><g class="m-lean"><?= $art ?></g></g></g>
				</svg>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="auth-stage-text">
		<h2 data-reveal><?= e($visual_heading) ?></h2>
		<p data-reveal><?= e($visual_text) ?></p>
	</div>
</aside>
