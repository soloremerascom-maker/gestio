document.addEventListener('DOMContentLoaded', () => {
  const profileInputs = document.querySelectorAll('input[name="profile_type"]');
  const employeeFields = document.getElementById('employeeFields');
  const providerFields = document.getElementById('providerFields');
  const branchSelect = document.getElementById('branch');
  const otherBranchGroup = document.getElementById('otherBranchGroup');
  const otherBranchInput = document.getElementById('other_branch');

  function toggleProfileFields(value) {
    if (value === 'empleado') {
      employeeFields.classList.remove('hidden');
      providerFields.classList.add('hidden');
      branchSelect.required = true;
      document.getElementById('dni_legajo').required = true;
      document.getElementById('dni_provider').required = false;
      document.getElementById('company').required = false;
    } else {
      providerFields.classList.remove('hidden');
      employeeFields.classList.add('hidden');
      branchSelect.required = false;
      document.getElementById('dni_legajo').required = false;
      document.getElementById('dni_provider').required = true;
      document.getElementById('company').required = true;
    }
  }

  profileInputs.forEach(input => {
    input.addEventListener('change', (event) => {
      toggleProfileFields(event.target.value);
    });
  });

  toggleProfileFields(document.querySelector('input[name="profile_type"]:checked').value);

  branchSelect.addEventListener('change', () => {
    if (branchSelect.value === 'OTRO (Completar derecha)') {
      otherBranchGroup.classList.remove('hidden');
      otherBranchInput.required = true;
    } else {
      otherBranchGroup.classList.add('hidden');
      otherBranchInput.required = false;
      otherBranchInput.value = '';
    }
  });
});
