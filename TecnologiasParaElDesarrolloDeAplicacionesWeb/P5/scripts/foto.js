const albumView = document.getElementById('album-view');
const modalView = document.getElementById('modal-view');

// 1. Crear las miniaturas del álbum
for (const photoSrc of PHOTO_LIST) {
  const img = document.createElement('img');
  img.src = photoSrc;
  img.alt = 'Foto';
  img.addEventListener('click', () => openModal(photoSrc));
  albumView.appendChild(img);
}

// 2. Función para abrir el modal
function openModal(src) {
  const bigImg = document.createElement('img');
  bigImg.src = src;

  modalView.appendChild(bigImg);
  modalView.classList.remove('hidden');
  document.body.classList.add('no-scroll');

  modalView.style.top = window.pageYOffset + 'px';
}

// 3. Cerrar el modal al hacer clic
modalView.addEventListener('click', () => {
  modalView.classList.add('hidden');
  document.body.classList.remove('no-scroll');
  modalView.innerHTML = '';
});
