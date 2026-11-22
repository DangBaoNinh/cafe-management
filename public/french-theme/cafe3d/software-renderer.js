import * as THREE from './vendor/three.module.min.js';

// A CPU compatibility renderer for this static diorama, using the same geometry
// and camera as WebGL. It projects and depth-sorts faces, not an image sequence.
export function createSoftwareRenderer(canvas) {
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Canvas 2D unavailable');
  let width = 1,
    height = 1,
    cache = null;
  const viewProjection = new THREE.Matrix4();
  const light = new THREE.Vector3(-0.45, 0.82, 0.5).normalize();
  const temporary = new THREE.Matrix4();
  const worldMatrix = new THREE.Matrix4();
  const color = new THREE.Color();
  const vector = new THREE.Vector3();
  const normal = new THREE.Vector3();
  const a = new THREE.Vector3(),
    b = new THREE.Vector3(),
    c = new THREE.Vector3();
  const edge1 = new THREE.Vector3(),
    edge2 = new THREE.Vector3();
  function project(x, y, z) {
    vector.set(x, y, z).applyMatrix4(viewProjection);
    return [(vector.x * 0.5 + 0.5) * width, (-vector.y * 0.5 + 0.5) * height, vector.z];
  }
  function makeCache(scene) {
    scene.updateMatrixWorld(true);
    const faces = [],
      foliage = [];
    scene.traverse((object) => {
      if (!object.isMesh || !object.visible || object.geometry.type === 'PlaneGeometry') return;
      if (object.isInstancedMesh) {
        if (object.instanceMatrix.usage === THREE.DynamicDrawUsage) return;
        for (let i = 0; i < object.count; i++) {
          object.getMatrixAt(i, temporary);
          worldMatrix.multiplyMatrices(object.matrixWorld, temporary);
          const position = new THREE.Vector3().setFromMatrixPosition(worldMatrix);
          const scale = new THREE.Vector3().setFromMatrixScale(worldMatrix);
          const tint = object.material.color.clone();
          if (object.instanceColor) {
            object.getColorAt(i, color);
            tint.multiply(color);
          }
          foliage.push({
            position,
            radius:
              (object.geometry.type === 'ShapeGeometry'
                ? Math.max(scale.x, scale.y)
                : Math.max(scale.x, scale.y, scale.z)) * 0.85,
            color: '#' + tint.getHexString(),
          });
        }
        return;
      }
      const positions = object.geometry.attributes.position;
      const indices = object.geometry.index;
      const count = indices ? indices.count : positions.count;
      let base = object.material.color?.clone() || new THREE.Color('#d9c8af');
      if (object.material.map) {
        const repeat = object.material.map.repeat.x;
        base = new THREE.Color(repeat === 2 ? '#53616a' : repeat === 3 ? '#c5b7a2' : '#294e43');
      }
      for (let i = 0; i < count; i += 3) {
        a.fromBufferAttribute(positions, indices ? indices.getX(i) : i).applyMatrix4(
          object.matrixWorld,
        );
        b.fromBufferAttribute(positions, indices ? indices.getX(i + 1) : i + 1).applyMatrix4(
          object.matrixWorld,
        );
        c.fromBufferAttribute(positions, indices ? indices.getX(i + 2) : i + 2).applyMatrix4(
          object.matrixWorld,
        );
        edge1.subVectors(b, a);
        edge2.subVectors(c, a);
        normal.crossVectors(edge1, edge2).normalize();
        const shade = 0.76 + Math.max(0, normal.dot(light)) * 0.27;
        const tint = base.clone().multiplyScalar(shade);
        faces.push({
          vertices: [a.clone(), b.clone(), c.clone()],
          color: '#' + tint.getHexString(),
          doubleSide: object.material.side === THREE.DoubleSide,
        });
      }
    });
    return { faces, foliage };
  }
  return {
    isSoftware: true,
    invalidate() {
      cache = null;
    },
    shadowMap: {},
    capabilities: { getMaxAnisotropy: () => 1 },
    setPixelRatio() {},
    setSize(w, h) {
      width = Math.round(w);
      height = Math.round(h);
      canvas.width = width;
      canvas.height = height;
    },
    render(scene, camera) {
      if (!cache) cache = makeCache(scene);
      camera.updateMatrixWorld();
      viewProjection.multiplyMatrices(camera.projectionMatrix, camera.matrixWorldInverse);
      const draw = [];
      for (const face of cache.faces) {
        const points = face.vertices.map((v) => project(v.x, v.y, v.z));
        if (points.some((p) => p[2] < -1 || p[2] > 1)) continue;
        const area =
          (points[1][0] - points[0][0]) * (points[2][1] - points[0][1]) -
          (points[1][1] - points[0][1]) * (points[2][0] - points[0][0]);
        if (area >= 0 && !face.doubleSide) continue;
        draw.push({
          points,
          color: face.color,
          depth: (points[0][2] + points[1][2] + points[2][2]) / 3,
        });
      }
      for (const p of cache.foliage) {
        const center = project(p.position.x, p.position.y, p.position.z);
        const edge = project(p.position.x + p.radius, p.position.y, p.position.z);
        draw.push({
          center,
          radius: Math.max(1, Math.abs(center[0] - edge[0])),
          color: p.color,
          depth: center[2],
        });
      }
      draw.sort((a, b) => b.depth - a.depth);
      ctx.clearRect(0, 0, width, height);
      // A soft grounding shadow avoids the floating cutout effect.
      const origin = project(0, -0.6, 0);
      const shadow = ctx.createRadialGradient(
        origin[0],
        origin[1],
        5,
        origin[0],
        origin[1],
        width * 0.38,
      );
      shadow.addColorStop(0, '#695e4224');
      shadow.addColorStop(1, '#695e4200');
      ctx.fillStyle = shadow;
      ctx.fillRect(0, 0, width, height);
      for (const item of draw) {
        ctx.fillStyle = item.color;
        if (item.center) {
          ctx.beginPath();
          ctx.ellipse(
            item.center[0],
            item.center[1],
            item.radius,
            item.radius * 0.75,
            0,
            0,
            Math.PI * 2,
          );
          ctx.fill();
        } else {
          ctx.beginPath();
          ctx.moveTo(item.points[0][0], item.points[0][1]);
          ctx.lineTo(item.points[1][0], item.points[1][1]);
          ctx.lineTo(item.points[2][0], item.points[2][1]);
          ctx.closePath();
          ctx.fill();
        }
      }
      scene.traverse((object) => {
        if (
          !object.isInstancedMesh ||
          object.instanceMatrix.usage !== THREE.DynamicDrawUsage ||
          !object.visible
        )
          return;
        for (let i = 0; i < object.count; i++) {
          object.getMatrixAt(i, temporary);
          vector.setFromMatrixPosition(temporary);
          const point = project(vector.x, vector.y, vector.z);
          ctx.fillStyle = '#df8cabbb';
          ctx.beginPath();
          ctx.ellipse(point[0], point[1], 3, 1.7, i * 0.7, 0, Math.PI * 2);
          ctx.fill();
        }
      });
    },
    dispose() {
      cache = null;
      ctx.clearRect(0, 0, width, height);
    },
  };
}
