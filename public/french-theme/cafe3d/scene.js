import * as THREE from './vendor/three.module.min.js';
import { OrbitControls } from './vendor/OrbitControls.js';
import { createSoftwareRenderer } from './software-renderer.js';

const palette = {
  wall: '#eddfc8',
  trim: '#faf1de',
  green: '#294e43',
  roof: '#46515b',
  iron: '#364039',
  wood: '#72543d',
  stone: '#c2b39e',
  soil: '#988576',
};
const materialCache = new Map();
function material(color, metalness = 0, roughness = 0.85) {
  const key = `${color}-${metalness}-${roughness}`;
  if (!materialCache.has(key))
    materialCache.set(key, new THREE.MeshStandardMaterial({ color, metalness, roughness }));
  return materialCache.get(key);
}
function seededRandom(seed = 71) {
  let value = seed;
  return () => {
    value = (value * 16807) % 2147483647;
    return (value - 1) / 2147483646;
  };
}

/** Mount one isolated, disposable scene. Root must contain [data-viewport]. */
export function mountCafe(root) {
  if (root.dataset.mounted) return () => {};
  root.dataset.mounted = 'true';
  const embedded = root.dataset.embedded === 'true';
  const viewport = root.querySelector('[data-viewport]');
  const status = root.querySelector('[data-status]');
  const canvas = document.createElement('canvas');
  canvas.tabIndex = 0;
  canvas.setAttribute(
    'aria-label',
    'Quán cà phê Toàn 3D. Kéo để xoay, cuộn để thu phóng. Dùng phím mũi tên trái phải để xoay.',
  );
  let renderer;
  try {
    const probe = document.createElement('canvas');
    const context = probe.getContext('webgl2');
    if (!context) throw new Error('WebGL2 unavailable');
    context.getExtension('WEBGL_lose_context')?.loseContext();
    renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: true,
      powerPreference: 'low-power',
    });
  } catch {
    try {
      renderer = createSoftwareRenderer(canvas);
    } catch {
      root.classList.add('cafe3d-fallback');
      status.textContent = 'Thiết bị chưa bật được 3D. Bạn vẫn có thể xem hình minh họa quán.';
      root.querySelectorAll('[data-action], [data-view]').forEach((button) => {
        button.disabled = true;
      });
      return () => {
        delete root.dataset.mounted;
      };
    }
  }
  viewport.append(canvas);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.24;
  const scene = new THREE.Scene();
  renderer.setClearColor?.(0x000000, 0);
  const camera = new THREE.PerspectiveCamera(embedded ? 35 : 39, 1, 0.1, 100);
  const initialPosition = embedded ? new THREE.Vector3(14, 10, 17) : new THREE.Vector3(17, 12, 20);
  camera.position.copy(initialPosition);
  const controls = new OrbitControls(camera, canvas);
  controls.target.set(0, 2.2, 0);
  controls.enableDamping = true;
  controls.dampingFactor = 0.07;
  controls.enablePan = false;
  controls.minDistance = 15;
  controls.maxDistance = 36;
  controls.minPolarAngle = Math.PI / 5;
  controls.maxPolarAngle = Math.PI / 2.18;
  controls.autoRotateSpeed = 0.42;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let rotate = !reducedMotion.matches;
  let petalsEnabled = !reducedMotion.matches;
  controls.autoRotate = rotate;
  scene.add(new THREE.HemisphereLight('#fff9ea', '#9ba68d', 2.65));
  const sun = new THREE.DirectionalLight('#ffe4be', 3.7);
  sun.position.set(-8, 15, 10);
  sun.castShadow = true;
  sun.shadow.mapSize.set(2048, 2048);
  Object.assign(sun.shadow.camera, {
    left: -14,
    right: 14,
    top: 14,
    bottom: -14,
    near: 1,
    far: 45,
  });
  sun.shadow.normalBias = 0.035;
  sun.shadow.bias = -0.0001;
  sun.shadow.radius = 3;
  scene.add(sun);
  const fill = new THREE.DirectionalLight('#dce8ff', 1.2);
  fill.position.set(8, 7, -10);
  scene.add(fill);
  const world = new THREE.Group();
  scene.add(world);
  const geometries = new Set();
  const localMaterials = new Set();
  const textures = new Set();
  const random = seededRandom();
  const boxGeometry = new THREE.BoxGeometry(1, 1, 1);
  const sphereGeometry = new THREE.SphereGeometry(1, 8, 6);
  const cylinderGeometry = new THREE.CylinderGeometry(1, 1, 1, 12);
  geometries.add(boxGeometry);
  geometries.add(sphereGeometry);
  geometries.add(cylinderGeometry);
  function mesh(geometry, mat, position, scale = [1, 1, 1], parent = world) {
    const object = new THREE.Mesh(geometry, typeof mat === 'string' ? material(mat) : mat);
    object.position.set(...position);
    object.scale.set(...scale);
    object.castShadow = true;
    object.receiveShadow = true;
    parent.add(object);
    return object;
  }
  const segmentedBoxes = new Map();
  function box(position, scale, color, parent = world) {
    let geometry = boxGeometry;
    // Subdivide large surfaces for stable depth ordering in the CPU renderer.
    // WebGL retains the compact geometry and hardware depth buffer.
    if (renderer.isSoftware && Math.max(...scale) > 3) {
      const segments = scale.map((size) => Math.max(1, Math.min(20, Math.ceil(size))));
      const key = segments.join(':');
      if (!segmentedBoxes.has(key)) {
        const segmented = new THREE.BoxGeometry(1, 1, 1, ...segments);
        geometries.add(segmented);
        segmentedBoxes.set(key, segmented);
      }
      geometry = segmentedBoxes.get(key);
    }
    return mesh(geometry, color, position, scale, parent);
  }
  function cylinder(position, radius, height, color, parent = world) {
    return mesh(cylinderGeometry, color, position, [radius, height, radius], parent);
  }
  function sphere(position, scale, color, parent = world) {
    return mesh(sphereGeometry, color, position, scale, parent);
  }
  function tube(points, radius, color, parent = world) {
    const curve = new THREE.CatmullRomCurve3(points.map((p) => new THREE.Vector3(...p)));
    const geometry = new THREE.TubeGeometry(curve, 16, radius, 6, false);
    geometries.add(geometry);
    return mesh(geometry, color, [0, 0, 0], [1, 1, 1], parent);
  }
  function surfaceTexture(type) {
    const image = document.createElement('canvas');
    image.width = image.height = 512;
    const ctx = image.getContext('2d');
    ctx.fillStyle = type === 'slate' ? '#4e5960' : '#d1c5b1';
    ctx.fillRect(0, 0, 512, 512);
    for (let row = 0; row < 16; row++) {
      for (let col = -1; col < 13; col++) {
        const x = col * 44 + (row % 2) * 22;
        const y = row * 34;
        const lightness = type === 'slate' ? 29 + random() * 10 : 69 + random() * 12;
        ctx.fillStyle =
          type === 'slate' ? `hsl(207 9% ${lightness}%)` : `hsl(36 19% ${lightness}%)`;
        ctx.fillRect(x + 1, y + 1, 41, 31);
      }
    }
    const texture = new THREE.CanvasTexture(image);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(type === 'slate' ? 2 : 3, type === 'slate' ? 1 : 3);
    texture.anisotropy = Math.min(4, renderer.capabilities.getMaxAnisotropy());
    textures.add(texture);
    return texture;
  }
  const cobble = new THREE.MeshStandardMaterial({ map: surfaceTexture('stone'), roughness: 1 });
  localMaterials.add(cobble);
  const slate = new THREE.MeshStandardMaterial({ map: surfaceTexture('slate'), roughness: 0.9 });
  localMaterials.add(slate);
  const glass = new THREE.MeshStandardMaterial({
    color: '#5a827b',
    metalness: 0.35,
    roughness: 0.21,
  });
  localMaterials.add(glass);
  // Entire little garden is modeled on every side, so the rear view is fully usable.
  box([0, -0.35, 0], [16.3, 0.65, 13.3], '#c4b29a');
  box([0, -0.01, 0], [16.15, 0.1, 13.15], cobble);
  box([0, -0.63, 0], [15.7, 0.12, 12.7], '#a08d76');
  const ground = new THREE.Mesh(new THREE.PlaneGeometry(200, 200), material('#eee9df'));
  geometries.add(ground.geometry);
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.74;
  ground.receiveShadow = true;
  if (!embedded) scene.add(ground);
  // Limestone townhouse, cornices and moss-green joinery.
  box([0, 2.35, -1], [7.3, 4.6, 4.8], palette.wall);
  box([0, 0.24, -1], [7.6, 0.38, 5], '#cdbda2');
  box([0, 2.75, -1], [7.55, 0.17, 5], palette.trim);
  box([0, 4.6, -1], [7.8, 0.22, 5.25], palette.trim);
  box([0, 4.76, -1], [7.9, 0.12, 5.3], '#c9bca9');
  for (const x of [-3.57, 3.57]) {
    for (let y = 0.6; y < 4.5; y += 0.4) {
      box([x, y, 1.42], [0.35, 0.29, 0.13], palette.trim);
      box([x, y, -3.42], [0.35, 0.29, 0.13], palette.trim);
    }
  }
  // Steep French mansard roof, composed as a closed truncated hip roof.
  function hipRoof(y0, y1, lowerWidth, lowerDepth, upperWidth, upperDepth) {
    const vertices = [
      [-lowerWidth / 2, y0, -1 - lowerDepth / 2],
      [lowerWidth / 2, y0, -1 - lowerDepth / 2],
      [lowerWidth / 2, y0, -1 + lowerDepth / 2],
      [-lowerWidth / 2, y0, -1 + lowerDepth / 2],
      [-upperWidth / 2, y1, -1 - upperDepth / 2],
      [upperWidth / 2, y1, -1 - upperDepth / 2],
      [upperWidth / 2, y1, -1 + upperDepth / 2],
      [-upperWidth / 2, y1, -1 + upperDepth / 2],
    ];
    const ids = [
      0, 4, 5, 0, 5, 1, 1, 5, 6, 1, 6, 2, 2, 6, 7, 2, 7, 3, 3, 7, 4, 3, 4, 0, 4, 7, 6, 4, 6, 5,
    ];
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
      'position',
      new THREE.Float32BufferAttribute(
        ids.flatMap((i) => vertices[i]),
        3,
      ),
    );
    geometry.setAttribute(
      'uv',
      new THREE.Float32BufferAttribute(
        ids.flatMap(
          (_, i) =>
            [
              [0, 0],
              [0, 1],
              [1, 1],
              [0, 0],
              [1, 1],
              [1, 0],
            ][i % 6],
        ),
        2,
      ),
    );
    geometry.computeVertexNormals();
    geometries.add(geometry);
    mesh(geometry, slate, [0, 0, 0]);
  }
  hipRoof(4.83, 6.2, 8, 5.4, 6.15, 3.5);
  hipRoof(6.2, 6.6, 6.15, 3.5, 0.1, 0.1);
  box([2.25, 6.24, -2.3], [0.55, 1.4, 0.55], '#c2a58c');
  box([2.25, 6.96, -2.3], [0.72, 0.13, 0.72], '#7d786e');
  function windowUnit(x, y, z, width = 1.05, height = 1.45, parent = world) {
    box([x, y, z], [width + 0.22, height + 0.21, 0.15], palette.trim, parent);
    box([x, y, z + 0.09], [width, height, 0.08], glass, parent);
    box([x, y, z + 0.15], [0.055, height, 0.05], palette.trim, parent);
    box([x, y + 0.12, z + 0.15], [width, 0.045, 0.05], palette.trim, parent);
    box([x, y - height / 2 - 0.12, z + 0.08], [width + 0.38, 0.13, 0.3], palette.trim, parent);
    for (const side of [-1, 1]) {
      const sx = x + side * (width / 2 + 0.28);
      box([sx, y, z + 0.04], [0.38, height + 0.06, 0.09], palette.green, parent);
      for (let i = 0; i < 7; i++)
        box(
          [sx, y - height / 2 + 0.12 + (i * height) / 7, z + 0.1],
          [0.31, 0.033, 0.035],
          '#527366',
          parent,
        );
    }
  }
  for (const x of [-2.3, 0, 2.3]) windowUnit(x, 3.65, 1.43, 0.95, 1.3);
  for (const x of [-2.3, 0, 2.3]) {
    box([x, 5.35, 1.1], [1.1, 1.05, 0.65], palette.wall);
    box([x, 5.43, 1.46], [0.7, 0.74, 0.08], glass);
    box([x, 5.43, 1.53], [0.045, 0.74, 0.06], palette.trim);
    box([x, 5.42, 1.53], [0.7, 0.045, 0.06], palette.trim);
    const roof = box([x, 5.99, 1.15], [1.35, 0.12, 1.0], palette.roof);
    roof.rotation.z = 0;
  }
  // Front shop windows, entry and striped canvas awnings.
  for (const x of [-2.3, 2.3]) {
    box([x, 1.47, 1.46], [1.85, 2.05, 0.12], palette.green);
    box([x, 1.57, 1.54], [1.6, 1.62, 0.04], glass);
    for (const dx of [-0.43, 0.43]) box([x + dx, 1.57, 1.59], [0.045, 1.62, 0.04], palette.trim);
    box([x, 1.55, 1.59], [1.62, 0.045, 0.04], palette.trim);
    box([x, 0.52, 1.55], [1.6, 0.12, 0.05], '#37594b');
    for (let i = 0; i < 9; i++) {
      const awning = box(
        [x - 1 + i * 0.25, 2.64, 2.06],
        [0.25, 0.07, 1.25],
        i % 2 ? palette.trim : palette.green,
      );
      awning.rotation.x = 0.22;
      box([x - 1 + i * 0.25, 2.43, 2.68], [0.25, 0.24, 0.07], i % 2 ? palette.trim : palette.green);
    }
  }
  box([0, 1.38, 1.48], [1.25, 2.5, 0.14], palette.green);
  box([0, 1.58, 1.57], [0.96, 1.7, 0.06], glass);
  box([0, 1.58, 1.63], [0.05, 1.7, 0.04], palette.trim);
  box([0, 1.5, 1.63], [0.96, 0.045, 0.04], palette.trim);
  sphere([0.4, 1.1, 1.67], [0.055, 0.055, 0.055], '#b29a59');
  box([0, 0.15, 1.9], [1.7, 0.2, 0.65], '#d2c2a7');
  // Sides have their own windows and rear has an intimate terrace.
  for (const side of [-1, 1]) {
    const group = new THREE.Group();
    group.position.set(side * 3.68, 0, -1);
    group.rotation.y = (side * Math.PI) / 2;
    world.add(group);
    for (const x of [-1.15, 1.15]) {
      windowUnit(x, 3.65, 0, 0.78, 1.25, group);
      windowUnit(x, 1.4, 0, 0.75, 1.3, group);
    }
  }
  const rear = new THREE.Group();
  rear.position.set(0, 0, -3.47);
  rear.rotation.y = Math.PI;
  world.add(rear);
  for (const x of [-2.3, 0, 2.3]) windowUnit(x, 3.65, 0, 0.95, 1.3, rear);
  for (const x of [-2.25, 2.25]) windowUnit(x, 1.45, 0, 1.15, 1.5, rear);
  box([0, 1.35, 0], [1.2, 2.5, 0.13], palette.green, rear);
  box([0, 1.55, 0.09], [0.95, 1.65, 0.06], glass, rear);
  // Real text is drawn on the 3D sign rather than composited over the viewport.
  function signTexture() {
    const image = document.createElement('canvas');
    image.width = 1024;
    image.height = 256;
    const ctx = image.getContext('2d');
    ctx.fillStyle = palette.green;
    ctx.fillRect(0, 0, 1024, 256);
    ctx.strokeStyle = '#bdac7f';
    ctx.lineWidth = 4;
    ctx.strokeRect(12, 12, 1000, 232);
    ctx.fillStyle = '#f3e8ce';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = 'italic 145px Georgia';
    ctx.fillText('Toàn', 512, 124);
    const texture = new THREE.CanvasTexture(image);
    texture.colorSpace = THREE.SRGBColorSpace;
    textures.add(texture);
    return texture;
  }
  const signMat = new THREE.MeshStandardMaterial({ map: signTexture(), roughness: 0.8 });
  localMaterials.add(signMat);
  box([0, 2.73, 1.65], [1.95, 0.5, 0.13], signMat);
  // Garden furniture, all fully modeled.
  function chair(x, z, angle = 0) {
    const g = new THREE.Group();
    g.position.set(x, 0.1, z);
    g.rotation.y = angle;
    world.add(g);
    cylinder([0, 0.54, 0], 0.23, 0.08, '#b3986d', g);
    for (const a of [-1, 1])
      for (const b of [-1, 1]) cylinder([a * 0.15, 0.27, b * 0.15], 0.022, 0.5, palette.iron, g);
    tube(
      [
        [-0.21, 0.55, -0.14],
        [-0.23, 1, -0.19],
        [0, 1.13, -0.2],
        [0.23, 1, -0.19],
        [0.21, 0.55, -0.14],
      ],
      0.026,
      palette.iron,
      g,
    );
    box([0, 0.87, -0.19], [0.35, 0.13, 0.05], '#b3986d', g);
  }
  function table(x, z) {
    cylinder([x, 0.82, z], 0.6, 0.09, '#f4ead8');
    cylinder([x, 0.43, z], 0.047, 0.74, palette.iron);
    for (let i = 0; i < 3; i++) {
      const angle = (i * Math.PI * 2) / 3;
      tube(
        [
          [x, 0.4, z],
          [x + Math.cos(angle) * 0.12, 0.16, z + Math.sin(angle) * 0.12],
          [x + Math.cos(angle) * 0.35, 0.1, z + Math.sin(angle) * 0.35],
        ],
        0.026,
        palette.iron,
      );
    }
    cylinder([x + 0.15, 0.9, z + 0.1], 0.07, 0.08, palette.trim);
    cylinder([x + 0.15, 0.945, z + 0.1], 0.052, 0.006, '#4b2f25');
    cylinder([x - 0.18, 0.99, z - 0.08], 0.05, 0.24, '#b2b8a0');
    sphere([x - 0.18, 1.15, z - 0.08], [0.11, 0.08, 0.1], '#d783a0');
  }
  for (const [x, z] of [
    [-2.45, 4.05],
    [2.5, 4.1],
    [5.9, 1.8],
    [-5.9, -0.9],
    [0, -5.3],
  ]) {
    table(x, z);
    chair(x - 0.87, z, Math.PI / 2);
    chair(x + 0.87, z, -Math.PI / 2);
  }
  function planter(x, z, radius = 0.4) {
    const geometry = new THREE.CylinderGeometry(radius, radius * 0.68, 0.65, 12);
    geometries.add(geometry);
    mesh(geometry, '#b77558', [x, 0.43, z]);
    cylinder([x, 0.76, z], radius * 0.9, 0.04, palette.soil);
    cylinder([x, 0.72, z], radius * 1.03, 0.1, '#c28365');
  }
  for (const x of [-0.96, 0.96]) {
    planter(x, 1.98, 0.25);
    sphere([x, 1.07, 1.98], [0.28, 0.38, 0.28], '#527453');
  }
  // Instanced bougainvillea leaves and papery bracts keep draw calls low.
  const leafGeometry = new THREE.SphereGeometry(1, 5, 4);
  geometries.add(leafGeometry);
  const leafMat = material('#50794c');
  const flowerMat = new THREE.MeshStandardMaterial({
    color: '#ffffff',
    roughness: 0.85,
    side: THREE.DoubleSide,
  });
  localMaterials.add(flowerMat);
  const petalShape = new THREE.Shape();
  petalShape.moveTo(0, -0.7);
  petalShape.bezierCurveTo(-1.1, 0.05, -0.8, 0.85, 0, 0.72);
  petalShape.bezierCurveTo(0.8, 0.85, 1.1, 0.05, 0, -0.7);
  const flowerGeometry = new THREE.ShapeGeometry(petalShape, 5);
  geometries.add(flowerGeometry);
  const flowers = new THREE.InstancedMesh(flowerGeometry, flowerMat, 3000);
  const leaves = new THREE.InstancedMesh(leafGeometry, leafMat, 1700);
  const dummy = new THREE.Object3D();
  let flowerCount = 0;
  let leafCount = 0;
  const flowerColors = ['#df6995', '#ea9ab5', '#c34f86', '#f2b4c8', '#e777a4'].map(
    (c) => new THREE.Color(c),
  );
  function blossom(cx, cy, cz, size = 0.5) {
    for (let i = 0; i < 36; i++) {
      const angle = random() * Math.PI * 2,
        height = (random() - 0.5) * 1.4;
      const radius = Math.sqrt(Math.max(0, 1 - height * height)) * size * Math.cbrt(random());
      const x = cx + Math.cos(angle) * radius,
        y = cy + height * size,
        z = cz + Math.sin(angle) * radius;
      if (flowerCount < 3000) {
        dummy.position.set(x, y, z);
        dummy.rotation.set(random() * Math.PI, random() * Math.PI * 2, random() * Math.PI * 2);
        const s = 0.11 + random() * 0.08;
        dummy.scale.set(s, s, 1);
        dummy.updateMatrix();
        flowers.setMatrixAt(flowerCount, dummy.matrix);
        flowers.setColorAt(flowerCount, flowerColors[Math.floor(random() * flowerColors.length)]);
        flowerCount++;
      }
      if (i % 2 === 0 && leafCount < 1700) {
        dummy.position.set(x, y - 0.06, z);
        dummy.rotation.set(random() * 3, random() * 3, random() * 3);
        dummy.scale.set(0.12, 0.07, 0.22);
        dummy.updateMatrix();
        leaves.setMatrixAt(leafCount++, dummy.matrix);
      }
    }
  }
  function floweringTree(x, z, height = 3.5) {
    planter(x, z, 0.47);
    tube(
      [
        [x, 0.7, z],
        [x - 0.13, 1.5, z + 0.03],
        [x + 0.17, 2.4, z],
        [x, height, z],
      ],
      0.065,
      '#725b42',
    );
    for (let i = 0; i < 6; i++) {
      const a = (i / 6) * Math.PI * 2;
      const tx = x + Math.cos(a) * 0.7,
        tz = z + Math.sin(a) * 0.65;
      tube(
        [
          [x, 1.8, z],
          [tx, height - 0.4, tz],
          [tx, height, tz],
        ],
        0.028,
        '#725b42',
      );
      blossom(tx, height, tz, 0.68);
    }
    blossom(x, height + 0.35, z, 0.75);
  }
  floweringTree(-5.8, 3.2, 3.5);
  floweringTree(5.8, -3.6, 3.9);
  floweringTree(-5.9, -4.5, 3.4);
  // A flower-covered pergola frames the left garden, plus vines on the facade.
  for (const z of [-2.9, 1.2]) {
    for (const x of [-7.1, -4.55]) box([x, 1.6, z], [0.1, 3.1, 0.1], palette.green);
    box([-5.8, 3.15, z], [2.85, 0.1, 0.1], palette.green);
  }
  for (const x of [-7.1, -4.55]) box([x, 3.15, -0.85], [0.1, 0.1, 4.4], palette.green);
  for (let z = -3; z <= 1.4; z += 0.55) {
    box([-5.8, 3.25, z], [2.9, 0.08, 0.07], palette.green);
    blossom(-7.05, 3.25, z, 0.48);
    blossom(-4.65, 3.25, z, 0.48);
  }
  for (const x of [-3.5, 3.5]) {
    tube(
      [
        [x, 0.2, 1.62],
        [x - 0.15, 1.8, 1.65],
        [x + 0.13, 3.4, 1.63],
        [x, 4.5, 1.65],
      ],
      0.035,
      '#745c43',
    );
    for (let y = 0.9; y < 4.9; y += 0.45) blossom(x, y, 1.65, 0.45);
  }
  for (let x = -3.2; x < 3.3; x += 0.55) blossom(x, 4.47, 1.65, 0.43);
  // Garden border in flower, visible from the rear.
  for (let x = -6.8; x <= 6.8; x += 0.8) {
    box([x, 0.22, -5.98], [0.7, 0.26, 0.65], '#9d9f74');
    blossom(x, 0.72, -5.98, 0.43);
  }
  flowers.count = flowerCount;
  leaves.count = leafCount;
  flowers.instanceMatrix.needsUpdate = true;
  leaves.instanceMatrix.needsUpdate = true;
  leaves.castShadow = true;
  flowers.castShadow = false;
  world.add(flowers, leaves);
  // Iron perimeter, lamps and a fountain give each view something to discover.
  for (const x of [-7.55, 7.55]) {
    for (let z = -5.6; z <= 5.5; z += 0.4) {
      cylinder([x, 0.62, z], 0.023, 1.1, palette.iron);
      sphere([x, 1.2, z], [0.045, 0.06, 0.045], palette.iron);
    }
    for (const y of [0.34, 0.98]) box([x, y, 0], [0.04, 0.04, 11.5], palette.iron);
  }
  for (const x of [-6.8, 6.8]) {
    cylinder([x, 1.5, 4.8], 0.055, 2.8, palette.iron);
    cylinder([x, 0.22, 4.8], 0.16, 0.25, palette.iron);
    box([x, 2.95, 4.8], [0.33, 0.46, 0.33], '#f7d9a5');
    for (const dx of [-0.18, 0.18])
      for (const dz of [-0.18, 0.18]) cylinder([x + dx, 2.95, 4.8 + dz], 0.018, 0.5, palette.iron);
    const roof = new THREE.ConeGeometry(0.34, 0.24, 4);
    geometries.add(roof);
    const cap = mesh(roof, palette.iron, [x, 3.3, 4.8]);
    cap.rotation.y = Math.PI / 4;
  }
  cylinder([5.9, 0.28, -0.8], 0.98, 0.3, '#c6b69e');
  cylinder([5.9, 0.44, -0.8], 0.82, 0.06, '#8db5ac');
  cylinder([5.9, 0.8, -0.8], 0.15, 0.7, '#c6b69e');
  cylinder([5.9, 1.12, -0.8], 0.48, 0.12, '#d8c9af');
  sphere([5.9, 1.4, -0.8], [0.13, 0.22, 0.13], '#bdd1c4');
  // Loose bracts on the paving.
  for (let i = 0; i < 65; i++) {
    const p = mesh(
      flowerGeometry,
      material(i % 2 ? '#d8789c' : '#edb1c0'),
      [(random() - 0.5) * 14, 0.055, (random() - 0.5) * 11],
      [0.07, 0.07, 0.07],
    );
    p.rotation.set(-Math.PI / 2, 0, random() * 6);
  }
  const falling = new THREE.InstancedMesh(flowerGeometry, material('#df8cab'), 64);
  falling.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  world.add(falling);
  const particles = Array.from({ length: 64 }, () => ({
    x: (random() - 0.5) * 16,
    y: random() * 8,
    z: (random() - 0.5) * 12,
    speed: 0.25 + random() * 0.45,
    phase: random() * Math.PI * 2,
    size: 0.055 + random() * 0.06,
  }));
  let disposed = false,
    visible = true,
    pageVisible = !document.hidden,
    frame = 0,
    last = 0,
    time = 0;
  let desiredView = null;
  const viewPositions = {
    front: new THREE.Vector3(13, 9.5, 21),
    garden: new THREE.Vector3(-19, 10.5, 12),
    rear: new THREE.Vector3(-12, 10, -21),
  };
  const home = initialPosition.clone();
  if (embedded) Object.values(viewPositions).forEach(position => position.multiplyScalar(0.86));
  const listeners = [];
  function listen(target, event, fn, options) {
    target.addEventListener(event, fn, options);
    listeners.push(() => target.removeEventListener(event, fn, options));
  }
  function syncButtons() {
    root.querySelector('[data-action="rotate"]').setAttribute('aria-pressed', String(rotate));
    root
      .querySelector('[data-action="petals"]')
      .setAttribute('aria-pressed', String(petalsEnabled));
    root.querySelector('[data-action="rotate"] [data-label]').textContent = rotate
      ? 'Dừng xoay'
      : 'Tự xoay';
    root.querySelector('[data-action="petals"] [data-label]').textContent = petalsEnabled
      ? 'Cánh hoa bật'
      : 'Cánh hoa tắt';
  }
  function requestFrame() {
    if (!frame && visible && pageVisible && !disposed) frame = requestAnimationFrame(animate);
  }
  function resize() {
    const rect = viewport.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return;
    camera.aspect = rect.width / rect.height;
    if (embedded) camera.zoom = Math.min(1.08, Math.max(0.62, camera.aspect * 0.78));
    camera.updateProjectionMatrix();
    renderer.setSize(rect.width, rect.height, false);
    requestFrame();
  }
  function animate(now) {
    frame = 0;
    if (disposed || !visible || !pageVisible) return;
    if (renderer.isSoftware && last && now - last < 66) {
      requestFrame();
      return;
    }
    const delta = last ? Math.min((now - last) / 1000, 0.1) : 0;
    last = now;
    time += delta;
    if (desiredView) {
      const current = new THREE.Spherical().setFromVector3(
        camera.position.clone().sub(controls.target),
      );
      const target = new THREE.Spherical().setFromVector3(desiredView.clone().sub(controls.target));
      const angle = Math.atan2(
        Math.sin(target.theta - current.theta),
        Math.cos(target.theta - current.theta),
      );
      current.theta += angle * 0.1;
      current.phi += (target.phi - current.phi) * 0.1;
      current.radius += (target.radius - current.radius) * 0.1;
      camera.position.setFromSpherical(current).add(controls.target);
      if (camera.position.distanceTo(desiredView) < 0.025) desiredView = null;
    }
    const cameraMoved = controls.update(delta);
    falling.visible = petalsEnabled;
    if (petalsEnabled) {
      particles.forEach((p, i) => {
        p.y -= delta * p.speed;
        if (p.y < 0.12) {
          p.y = 7 + random();
          p.x = (random() - 0.5) * 15;
        }
        dummy.position.set(
          p.x + Math.sin(time * 0.6 + p.phase) * 0.7,
          p.y,
          p.z + Math.cos(time * 0.5 + p.phase) * 0.3,
        );
        dummy.rotation.set(time + p.phase, Math.sin(time + p.phase), time * 0.7 + p.phase);
        dummy.scale.set(p.size, p.size, p.size);
        dummy.updateMatrix();
        falling.setMatrixAt(i, dummy.matrix);
      });
      falling.instanceMatrix.needsUpdate = true;
    }
    renderer.render(scene, camera);
    if (rotate || petalsEnabled || desiredView || cameraMoved) requestFrame();
  }
  listen(controls, 'change', requestFrame);
  function clearViews() {
    root
      .querySelectorAll('[data-view]')
      .forEach((button) => button.setAttribute('aria-pressed', 'false'));
  }
  listen(controls, 'start', () => {
    desiredView = null;
    rotate = false;
    controls.autoRotate = false;
    clearViews();
    syncButtons();
  });
  root.querySelectorAll('[data-action]').forEach((button) =>
    listen(button, 'click', () => {
      const action = button.dataset.action;
      if (action === 'rotate') {
        rotate = !rotate;
        controls.autoRotate = rotate;
      }
      if (action === 'petals') petalsEnabled = !petalsEnabled;
      if (action === 'reset') {
        clearViews();
        desiredView = home.clone();
        controls.target.set(0, 2.2, 0);
        rotate = false;
        controls.autoRotate = false;
      }
      syncButtons();
      requestFrame();
    }),
  );
  root.querySelectorAll('[data-view]').forEach((button) =>
    listen(button, 'click', () => {
      rotate = false;
      controls.autoRotate = false;
      desiredView = viewPositions[button.dataset.view].clone();
      root
        .querySelectorAll('[data-view]')
        .forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      syncButtons();
      requestFrame();
    }),
  );
  listen(canvas, 'keydown', (event) => {
    if (!['ArrowLeft', 'ArrowRight', '+', '-', 'Home'].includes(event.key)) return;
    event.preventDefault();
    clearViews();
    desiredView = null;
    rotate = false;
    controls.autoRotate = false;
    if (event.key === 'Home') desiredView = home.clone();
    else {
      const offset = camera.position.clone().sub(controls.target);
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight')
        offset.applyAxisAngle(new THREE.Vector3(0, 1, 0), event.key === 'ArrowLeft' ? -0.15 : 0.15);
      else
        offset
          .multiplyScalar(event.key === '+' ? 0.9 : 1.1)
          .clampLength(controls.minDistance, controls.maxDistance);
      camera.position.copy(controls.target).add(offset);
    }
    syncButtons();
    requestFrame();
  });
  listen(document, 'visibilitychange', () => {
    pageVisible = !document.hidden;
    last = 0;
    if (!pageVisible) {
      cancelAnimationFrame(frame);
      frame = 0;
    } else requestFrame();
  });
  listen(reducedMotion, 'change', () => {
    rotate = !reducedMotion.matches;
    petalsEnabled = !reducedMotion.matches;
    controls.autoRotate = rotate;
    syncButtons();
    requestFrame();
  });
  const resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(viewport);
  const intersectionObserver = new IntersectionObserver(
    (entries) => {
      visible = entries[0].isIntersecting;
      last = 0;
      if (!visible) {
        cancelAnimationFrame(frame);
        frame = 0;
      } else requestFrame();
    },
    { rootMargin: '80px' },
  );
  intersectionObserver.observe(root);
  listen(canvas, 'webglcontextlost', (event) => {
    event.preventDefault();
    cancelAnimationFrame(frame);
    frame = 0;
    root.classList.add('cafe3d-fallback');
    status.textContent = 'Khung cảnh 3D đã tạm dừng. Tải lại trang để mở lại.';
  });
  root.classList.add('cafe3d-ready');
  status.textContent = renderer.isSoftware
    ? 'Kéo để xoay · Chế độ tương thích'
    : 'Kéo để xoay · Cuộn hoặc chụm hai ngón để thu phóng';
  syncButtons();
  resize();
  function dispose() {
    if (disposed) return;
    disposed = true;
    cancelAnimationFrame(frame);
    controls.dispose();
    resizeObserver.disconnect();
    intersectionObserver.disconnect();
    listeners.forEach((remove) => remove());
    geometries.forEach((g) => g.dispose());
    localMaterials.forEach((m) => m.dispose());
    textures.forEach((t) => t.dispose());
    flowers.dispose();
    leaves.dispose();
    falling.dispose();
    renderer.dispose();
    canvas.remove();
    delete root.dataset.mounted;
  }
  listen(window, 'pagehide', (event) => {
    if (!event.persisted) dispose();
  });
  return dispose;
}
