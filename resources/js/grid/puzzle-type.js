// Mirrors app/Enums/PuzzleType.php on the JS side. Each capability flag answers
// a question the editor needs to ask without branching on the type name.

const STANDARD = {
    name: 'standard',
    enforceSymmetry: true,
    allowSymmetryToggle: true,
    hasFixedVoids: false,
    hasGridLock: false,
    uniformBorders: false,
};

const DIAMOND = {
    name: 'diamond',
    enforceSymmetry: true,
    allowSymmetryToggle: false,
    hasFixedVoids: true,
    hasGridLock: false,
    uniformBorders: false,
};

const FREESTYLE = {
    name: 'freestyle',
    enforceSymmetry: false,
    allowSymmetryToggle: false,
    hasFixedVoids: false,
    hasGridLock: true,
    // Every cell edge — including the outer perimeter — draws at the same thin
    // weight as the seams between used cells, rather than a thick outline.
    uniformBorders: true,
};

const BY_NAME = {
    standard: STANDARD,
    diamond: DIAMOND,
    freestyle: FREESTYLE,
};

export function puzzleTypeCapabilities(name) {
    return BY_NAME[name] ?? STANDARD;
}
