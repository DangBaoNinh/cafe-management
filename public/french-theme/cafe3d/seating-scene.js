import * as THREE from './vendor/three.module.min.js';
import { OrbitControls } from './vendor/OrbitControls.js';
import { createSoftwareRenderer } from './software-renderer.js';

// Three-dimensional seated figures share the table geometry and perspective.
// They are anonymous stylized models, never customer photos or identity data.
export function mountSeatingScene(root, onSelect) {
  const canvas = document.createElement('canvas');
  canvas.tabIndex = 0;
  canvas.setAttribute(
    'aria-label',
    'Không gian bàn 3D. Kéo để xoay, chạm bàn để xem. Danh sách bàn bên cạnh hỗ trợ bàn phím.',
  );
  const probe = document.createElement('canvas');
  const context = probe.getContext('webgl2');
  let renderer;
  if (context) {
    context.getExtension('WEBGL_lose_context')?.loseContext();
    try {
      renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    } catch {
      renderer = createSoftwareRenderer(canvas);
    }
  } else renderer = createSoftwareRenderer(canvas);
  root.append(canvas);
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 1.5));
  renderer.shadowMap.enabled = !renderer.isSoftware;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
  camera.position.set(8.8, 8.3, 11.5);
  const controls = new OrbitControls(camera, canvas);
  controls.target.set(0, -0.45, 0);
  controls.enablePan = false;
  controls.enableDamping = true;
  controls.minDistance = 8;
  controls.maxDistance = 23;
  controls.minPolarAngle = 0.35;
  controls.maxPolarAngle = 1.4;
  scene.add(new THREE.HemisphereLight('#fff8eb', '#a6b1a0', 2.8));
  const sun = new THREE.DirectionalLight('#ffe8c7', 3.2);
  sun.position.set(-6, 12, 6);
  sun.castShadow = true;
  Object.assign(sun.shadow.camera, { left: -8, right: 8, top: 8, bottom: -8, near: 1, far: 35 });
  sun.shadow.mapSize.set(1024, 1024);
  sun.shadow.normalBias = 0.03;
  scene.add(sun);
  let world = null;
  const geometries = new Set();
  const materials = new Map();
  const boxGeometry = new THREE.BoxGeometry(1, 1, 1);
  const sphereGeometry = new THREE.SphereGeometry(1, 10, 8);
  const cylinderGeometry = new THREE.CylinderGeometry(1, 1, 1, 16);
  const capsuleGeometry = new THREE.CapsuleGeometry(1, 1, 3, 8);
  [boxGeometry, sphereGeometry, cylinderGeometry, capsuleGeometry].forEach((item) =>
    geometries.add(item),
  );
  const hitTargets = [];
  const markers = [];
  const colors = {
    available: '#709373',
    occupied: '#c29c6a',
    reserved: '#ba849a',
    cleaning: '#809daa',
    maintenance: '#a5a69d',
    unknown: '#a5a69d',
    conflict: '#a5a69d',
  };
  function mat(color) {
    if (!materials.has(color))
      materials.set(color, new THREE.MeshStandardMaterial({ color, roughness: 0.78 }));
    return materials.get(color);
  }
  function mesh(geometry, color, position, scale, parent = world) {
    const object = new THREE.Mesh(geometry, mat(color));
    object.position.set(...position);
    object.scale.set(...scale);
    object.castShadow = true;
    object.receiveShadow = true;
    parent.add(object);
    return object;
  }
  function box(position, scale, color, parent) {
    return mesh(boxGeometry, color, position, scale, parent);
  }
  function sphere(position, scale, color, parent) {
    return mesh(sphereGeometry, color, position, scale, parent);
  }
  function cylinder(position, radius, height, color, parent) {
    return mesh(cylinderGeometry, color, position, [radius, height, radius], parent);
  }
  function limb(start, end, radius, color, parent) {
    const a = new THREE.Vector3(...start),
      b = new THREE.Vector3(...end);
    const object = mesh(
      capsuleGeometry,
      color,
      a.clone().add(b).multiplyScalar(0.5).toArray(),
      [radius, Math.max(0.025, a.distanceTo(b) / 3), radius],
      parent,
    );
    object.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), b.sub(a).normalize());
    return object;
  }
  function chair(x, z, angle, person, index) {
    const group = new THREE.Group();
    group.position.set(x, 0, z);
    group.rotation.y = angle;
    world.add(group);
    cylinder([0, 0.47, 0], 0.24, 0.075, '#ab8055', group);
    for (const side of [-1, 1])
      for (const depth of [-1, 1])
        limb(
          [side * 0.15, 0.44, depth * 0.14],
          [side * 0.2, 0.04, depth * 0.2],
          0.018,
          '#52614e',
          group,
        );
    for (const side of [-1, 1])
      limb([side * 0.2, 0.45, -0.15], [side * 0.2, 0.97, -0.21], 0.018, '#52614e', group);
    box([0, 0.86, -0.2], [0.36, 0.19, 0.035], '#b08d64', group);
    if (!person) return;
    const skin = ['#d3a079', '#e4b495', '#b57d59', '#dca783'][index % 4];
    const outfit = ['#647b68', '#d2bba5', '#a26f73', '#677b91', '#e6ddc7'][index % 5];
    const pants = index % 2 ? '#445562' : '#514b42';
    sphere([0, 0.61, 0.02], [0.22, 0.15, 0.2], pants, group);
    sphere([0, 0.87, 0], [0.215, 0.31, 0.14], outfit, group);
    cylinder([0, 1.14, 0], 0.065, 0.14, skin, group);
    sphere([0, 1.31, 0.025], [0.135, 0.18, 0.13], skin, group);
    sphere([0, 1.39, -0.015], [0.142, 0.12, 0.14], index % 3 ? '#49372c' : '#252b2a', group);
    if (index % 2 === 0) sphere([0, 1.23, -0.07], [0.14, 0.16, 0.095], '#49372c', group);
    sphere([0, 1.29, 0.155], [0.026, 0.035, 0.035], skin, group);
    for (const side of [-1, 1]) {
      sphere([side * 0.047, 1.34, 0.145], [0.012, 0.012, 0.008], '#2f2c28', group);
      sphere([side * 0.138, 1.3, 0.02], [0.026, 0.04, 0.025], skin, group);
      limb([side * 0.17, 1.03, 0], [side * 0.23, 0.8, 0.13], 0.065, outfit, group);
      limb([side * 0.23, 0.8, 0.13], [side * 0.14, 0.82, 0.43], 0.044, skin, group);
      sphere([side * 0.14, 0.82, 0.45], [0.055, 0.035, 0.075], skin, group);
      limb([side * 0.12, 0.59, 0.07], [side * 0.15, 0.53, 0.34], 0.085, pants, group);
      limb([side * 0.15, 0.53, 0.34], [side * 0.15, 0.12, 0.36], 0.068, pants, group);
      sphere([side * 0.15, 0.08, 0.42], [0.09, 0.075, 0.16], '#4c3c30', group);
    }
  }
  function pot(x, z) {
    cylinder([x, 0.22, z], 0.27, 0.45, '#be9478');
    limb([x, 0.4, z], [x, 1.2, z], 0.035, '#817254', world);
    for (let i = 0; i < 7; i++) {
      const angle = i * 2.4;
      sphere(
        [x + Math.cos(angle) * 0.24, 1.1 + (i % 3) * 0.15, z + Math.sin(angle) * 0.24],
        [0.25, 0.19, 0.25],
        '#789367',
      );
      sphere(
        [x + Math.cos(angle) * 0.3, 1.25 + (i % 3) * 0.15, z + Math.sin(angle) * 0.3],
        [0.12, 0.1, 0.13],
        '#d49ab0',
      );
    }
  }
  function build(snapshotTables, areaName) {
    if (world) scene.remove(world);
    world = new THREE.Group();
    scene.add(world);
    hitTargets.length = 0;
    markers.splice(0).forEach((marker) => marker.element.remove());
    // Small parquet planks also avoid large intersecting faces in CPU mode.
    for (let row = 0; row < 10; row++)
      for (let col = 0; col < 12; col++) {
        box(
          [-3.92 + col * 0.71, -0.1, -3.25 + row * 0.71],
          [0.69, 0.28, 0.69],
          (row + col) % 3 ? '#dfcdb3' : '#d4c0a3',
        );
      }
    // Open-front dollhouse section with glazed garden doors at the rear.
    for (let i = 0; i < 12; i++) box([-3.92 + i * 0.71, 0.43, -3.5], [0.69, 0.82, 0.12], '#eee5d3');
    for (const x of [-3.8, -1.9, 0, 1.9, 3.8]) {
      box([x, 1.75, -3.5], [0.1, 2.65, 0.12], '#54715b');
      box([x, 2.98, -3.5], [1.85, 0.1, 0.14], '#54715b');
      box([x, 1.8, -3.5], [1.8, 0.06, 0.12], '#91a18a');
    }
    for (const x of [-3.8, 3.8]) {
      pot(x, -2.8);
      pot(x, 2.7);
    }
    // Window rail and a trailing bougainvillea canopy.
    for (let i = 0; i < 25; i++)
      sphere(
        [-4 + i / 3, 3.1 + Math.sin(i) * 0.06, -3.5],
        [0.19, 0.1, 0.18],
        i % 3 ? '#c98aa6' : '#8d9e76',
      );
    snapshotTables.slice(0, 6).forEach((table, index) => {
      const x = ((index % 3) - 1) * 2.6;
      const z = (Math.floor(index / 3) - 0.5) * 2.8;
      const tableTop = cylinder([x, 0.78, z], 0.59, 0.09, '#f7f2e7');
      tableTop.userData.tableId = table.id;
      hitTargets.push(tableTop);
      cylinder([x, 0.4, z], 0.055, 0.72, '#65705e');
      cylinder([x, 0.08, z], 0.3, 0.06, '#65705e');
      cylinder([x, 0.7, z], 0.18, 0.03, colors[table.status] || colors.unknown);
      const seats = Math.min(table.capacity, 6);
      for (let seat = 0; seat < seats; seat++) {
        const angle = (seat * Math.PI * 2) / seats;
        const sx = x + Math.sin(angle) * 1.0,
          sz = z + Math.cos(angle) * 1.0;
        chair(
          sx,
          sz,
          angle + Math.PI,
          table.status === 'occupied' && seat < Math.max(1, table.seated_count),
          index * 3 + seat,
        );
      }
      if (table.status === 'occupied') {
        cylinder([x + 0.23, 0.86, z + 0.17], 0.07, 0.1, '#f6e8cb');
        cylinder([x + 0.23, 0.915, z + 0.17], 0.055, 0.008, '#664631');
        box([x - 0.16, 0.845, z], [0.23, 0.025, 0.29], '#a38c6d');
      }
      if (table.status === 'reserved') {
        const card = box([x, 0.94, z], [0.24, 0.21, 0.025], '#d2a2b7');
        card.rotation.x = -0.25;
      }
      if (table.status === 'cleaning') {
        box([x, 0.85, z], [0.32, 0.025, 0.32], '#a7c0c2');
        cylinder([x + 0.2, 0.96, z], 0.055, 0.23, '#75929a');
      }
      const marker = document.createElement('button');
      marker.className = `seat-marker ${table.status}`;
      marker.textContent = table.code;
      marker.setAttribute('aria-label', `Xem bàn ${table.code}`);
      marker.addEventListener('click', () => onSelect(table.id));
      root.append(marker);
      markers.push({ element: marker, position: new THREE.Vector3(x, 1.67, z) });
    });
    root.setAttribute('data-current-area', areaName);
    renderer.invalidate?.();
    renderOnce();
  }
  let frame = 0,
    last = 0,
    visible = true,
    disposed = false;
  function renderOnce() {
    if (!frame && visible && !document.hidden && !disposed) frame = requestAnimationFrame(draw);
  }
  function draw(now) {
    frame = 0;
    if (disposed || !visible || document.hidden) return;
    if (renderer.isSoftware && last && now - last < 65) {
      renderOnce();
      return;
    }
    last = now;
    const moved = controls.update();
    renderer.render(scene, camera);
    const rect = root.getBoundingClientRect();
    markers.forEach((marker) => {
      const point = marker.position.clone().project(camera);
      marker.element.style.left = `${(point.x * 0.5 + 0.5) * rect.width}px`;
      marker.element.style.top = `${(-point.y * 0.5 + 0.5) * rect.height}px`;
      marker.element.hidden = point.z < -1 || point.z > 1;
    });
    if (moved) renderOnce();
  }
  let viewportWidth = 0, viewportHeight = 0;
  function resize() {
    const width = root.clientWidth, height = root.clientHeight;
    if (!width || !height || (width === viewportWidth && height === viewportHeight)) return;
    viewportWidth = width;
    viewportHeight = height;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
    renderOnce();
  }
  controls.addEventListener('change', renderOnce);
  const resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(root);
  const intersectionObserver = new IntersectionObserver((entries) => {
    visible = entries[0].isIntersecting;
    if (visible) renderOnce();
  });
  intersectionObserver.observe(root);
  const visibility = () => {
    if (!document.hidden) renderOnce();
  };
  document.addEventListener('visibilitychange', visibility);
  const raycaster = new THREE.Raycaster();
  let down = null;
  const pointerDown = (event) => {
    down = { x: event.clientX, y: event.clientY };
  };
  const pointerUp = (event) => {
    if (!down || Math.hypot(event.clientX - down.x, event.clientY - down.y) > 6) return;
    const rect = canvas.getBoundingClientRect();
    raycaster.setFromCamera(
      new THREE.Vector2(
        ((event.clientX - rect.left) / rect.width) * 2 - 1,
        (-(event.clientY - rect.top) / rect.height) * 2 + 1,
      ),
      camera,
    );
    const hit = raycaster.intersectObjects(hitTargets)[0];
    if (hit) onSelect(hit.object.userData.tableId);
  };
  canvas.addEventListener('pointerdown', pointerDown);
  canvas.addEventListener('pointerup', pointerUp);
  const keydown = (event) => {
    if (!['ArrowLeft', 'ArrowRight', '+', '-', 'Home'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Home') camera.position.set(8.8, 8.3, 11.5);
    else {
      const offset = camera.position.clone().sub(controls.target);
      if (event.key.startsWith('Arrow'))
        offset.applyAxisAngle(new THREE.Vector3(0, 1, 0), event.key === 'ArrowLeft' ? -0.16 : 0.16);
      else
        offset
          .multiplyScalar(event.key === '+' ? 0.9 : 1.1)
          .clampLength(controls.minDistance, controls.maxDistance);
      camera.position.copy(controls.target).add(offset);
    }
    controls.update();
    renderOnce();
  };
  canvas.addEventListener('keydown', keydown);
  resize();
  return {
    update: build,
    reset() {
      camera.position.set(8.8, 8.3, 11.5);
      controls.target.set(0, -0.45, 0);
      controls.update();
      renderOnce();
    },
    dispose() {
      disposed = true;
      cancelAnimationFrame(frame);
      controls.dispose();
      resizeObserver.disconnect();
      intersectionObserver.disconnect();
      document.removeEventListener('visibilitychange', visibility);
      canvas.removeEventListener('pointerdown', pointerDown);
      canvas.removeEventListener('pointerup', pointerUp);
      canvas.removeEventListener('keydown', keydown);
      geometries.forEach((geometry) => geometry.dispose());
      materials.forEach((material) => material.dispose());
      renderer.dispose();
      canvas.remove();
      markers.forEach((marker) => marker.element.remove());
    },
    compatibility: renderer.isSoftware,
  };
}
