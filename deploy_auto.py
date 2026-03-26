import sys
import unittest.mock

print("Running deployment automatically...")

# patch getpass.getpass and input
with unittest.mock.patch('getpass.getpass', return_value='03yeierpupsrj03'):
    with unittest.mock.patch('builtins.input', return_value='n'):
        # import deploy will execute the module level code
        # but we need to remove it from sys.modules if it was already imported, which it shouldn't be
        import deploy
