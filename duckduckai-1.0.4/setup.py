from setuptools import setup, find_packages
from pathlib import Path

long_description = (Path(__file__).parent / "README.md").read_text(encoding="utf-8")

setup(
    name="DuckDuckAI",  
    version="1.0.4",
    author="Ramona-Flower",
    description="A package to interact with DuckDuckGo AI-powered search",
    long_description=long_description,
    long_description_content_type="text/markdown", 
    url="https://github.com/ramona-flower/DuckDuckAI", 
    packages=find_packages(),
    install_requires=[
        "requests",
    ],
    classifiers=[
        "Programming Language :: Python :: 3",
        "License :: OSI Approved :: MIT License", 
        "Operating System :: OS Independent",
    ],
    python_requires=">=3.6",
)